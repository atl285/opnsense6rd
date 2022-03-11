#!/usr/local/bin/python3
#
# source is RFC-5969, Chapter 7.1.1.  6rd DHCPv4 Option
# https://datatracker.ietf.org/doc/html/rfc5969#section-7.1.1
#


# import needed libraries
import argparse
import ipaddress
import os
import sys

def read_leasefile(interface):
    """ read lease file for given interface and returns content
        of option-212 as string from the last found lease
    :param interface
    :return: string
    """
    new_6rd_config = False
    try:
        # Do something with the file
        with open("/var/db/dhclient.leases." + interface) as f:
            option_line = ""
            for line in f.readlines():
                if " option-212 " in line:
                    if option_line != "" and option_line != line:
                        # set flag if option 212 has changed
                        new_6rd_config = True
                    option_line = line

            if option_line is not None:
                option_value = (option_line.split())[2].replace(';', '')
                return [new_6rd_config, option_value]

    except IOError:
        print("File not accessible")
    return None


def convert_option212(value_string):
    """ convert options-212 string into 6RD configuration for OPNsense
    :param value_string
    :return: dictionary
    """
    # not sure if values always separated by ':'
    option_array = value_string.split(':')
    option_length = len(option_array)
    if option_length < 22:
        # less than minimal possible field length
        return None

    # IPv4MaskLen   The number of high-order bits that are identical
    #               across all CE IPv4 addresses within a given 6rd
    #               domain.  This may be any value between 0 and 32.
    #               Any value greater than 32 is invalid.
    sixrd_ip4_prefix_len = int(option_array[0], 16)
    if sixrd_ip4_prefix_len > 32:
        return None

    # 6rdPrefixLen  The IPv6 prefix length of the SP's 6rd IPv6
    #               prefix in number of bits.  For the purpose of
    #               bounds checking by DHCP option processing, the
    #               sum of (32 - IPv4MaskLen) + 6rdPrefixLen MUST be
    #               less than or equal to 128.
    sixrd_prefix_len = int(option_array[1], 16)
    delegation_prefix = 32 - sixrd_ip4_prefix_len + sixrd_prefix_len
    if delegation_prefix > 128:
        return None

    # 6rdPrefix  The service provider's 6rd IPv6 prefix
    #            represented as a 16-octet IPv6 address.  The bits
    #            in the prefix after the 6rdPrefixlen number of
    #            bits are reserved and MUST be initialized to zero
    #            by the sender and ignored by the receiver.
    sixrd_prefix = ""
    for index in [2, 4, 6, 8, 10, 12, 14, 16]:
        sixrd_prefix = sixrd_prefix + str('{:0>2s}'.format(option_array[index]) +
                                          '{:0>2s}:'.format(option_array[index + 1]))
    sixrd_prefix = str(ipaddress.ip_address(sixrd_prefix[:-1]).compressed)

    # 6rdBRIPv4Address  One or more IPv4 addresses of the 6rd Border
    #                   Relay(s) for a given 6rd domain.
    sixrd_border_relay = ""
    index = 18
    while index < option_length:
        sixrd_border_relay = str(int(option_array[index], 16)) + '.' + str(int(option_array[index + 1], 16)) + '.' + \
                             str(int(option_array[index + 2], 16)) + '.' + str(int(option_array[index + 3], 16))
        index += 4

    # return dictionary with values
    return ({'sixrd_prefix': sixrd_prefix + '/' + str(sixrd_prefix_len),
             'sixrd_border_relay': sixrd_border_relay,
             'sixrd_ip4_prefix_len': sixrd_ip4_prefix_len,
             'delegation_prefix': delegation_prefix})


if __name__ == '__main__':
    """ main function to run the file as program
    """
    new_option212 = None
    parser = argparse.ArgumentParser()
    parser.add_argument("-e", help="use environment for receiving input", action="store_true")
    parser.add_argument("interface", help="interface to look for a lease file", nargs="?")
    args = parser.parse_args()

    if args.interface:
        # get option 212 from lease file
        new_option212 = read_leasefile(str(args.interface))
    elif args.e:
        # get option 212 from environment variables
        if os.getenv('old_option_212') != os.environ.get('new_option_212'):
            new_option212 = [True, os.environ.get('new_option_212')]
        else:
            new_option212 = [False, os.environ.get('new_option_212')]

    if new_option212[0] == True and new_option212[1] != None:
        ipv6_config = convert_option212(new_option212[1])
        if ipv6_config:
            print('6RD Prefix:              ' + ipv6_config['sixrd_prefix'])
            print('6RD Border Relay:        ' + ipv6_config['sixrd_border_relay'])
            print('6RD IPv4 Prefix length:  ' + str(ipv6_config['sixrd_ip4_prefix_len']) + ' bits')
            print('Delegation Prefix:       ' + '/' + str(ipv6_config['delegation_prefix']))
            sys.exit(1)
    else:
        print('No changes.')

    # return 0 - no changes
    sys.exit(0)
