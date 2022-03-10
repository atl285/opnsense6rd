# This is a sample Python script.

# Press ⌃R to execute it or replace it with your code.
# Press Double ⇧ to search everywhere for classes, files, tool windows, actions, and settings.

# import needed libraries
import argparse
import ipaddress

# function to read dhcp.leases file for wan interface
# and get the value for Option 212 from the last lease
def read_leasefile(interface):
    try:
        # Do something with the file
        with open("/var/db/dhclient.leases." + interface) as f:
            for line in f.readlines():
                if " option-212 " in line:
                    option_line = line

            if option_line is not None:
                option_value = (option_line.split())[2].replace(';', '')
                return option_value

    except IOError:
        print("File not accessible")
    return None


# converting Option 212 to IPv6 configuration
# according to RFC5969
# https://datatracker.ietf.org/doc/html/rfc5969#section-7.1.1
def convert_option212(value_string):
    # not sure if values always separated by ':'
    option_array = value_string.split(':')
    option_length = len(option_array)
    if option_length < 22:
        # less than minimal possible field length
        return None

    # getting IPv4 Mask Length
    sixrd_ip4_prefix_len = int(option_array[0], 16)

    # getting IPv6 prefix length
    sixrd_prefix_len = int(option_array[1], 16)
    delegation_prefix = 32 - sixrd_ip4_prefix_len + sixrd_prefix_len
    if delegation_prefix > 128:
        # invalid delegation_prefix
        return None

    # getting the IPv6 6RD Prefix from the 3rd until 18th bytes
    sixrd_prefix = ""
    for index in [2, 4, 6, 8, 10, 12, 14, 16]:
        sixrd_prefix = sixrd_prefix + str('{:0>2s}'.format(option_array[index]) +
                                          '{:0>2s}:'.format(option_array[index + 1]))
    sixrd_prefix = str(ipaddress.ip_address(sixrd_prefix[:-1]).compressed)

    # getting one or more IPv4 addresses of the
    # 6RD Border Relay(s) for a given 6RD domain.
    sixrd_border_relay = ""
    index = 18
    while index < option_length:
        sixrd_border_relay = str(int(option_array[index], 16)) + '.' + str(int(option_array[index + 1], 16)) + '.' + \
                           str(int(option_array[index + 2], 16)) + '.' + str(int(option_array[index + 3], 16))
        index += 4

    # return array with values
    return ({'sixrd_prefix': sixrd_prefix + '/' + str(sixrd_prefix_len),
             'sixrd_border_relay': sixrd_border_relay,
             'sixrd_ip4_prefix_len': sixrd_ip4_prefix_len,
             'delegation_prefix': delegation_prefix})


# Press the green button in the gutter to run the script.
if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("interface", help="interface to look for a lease file")
    args = parser.parse_args()

    if args.interface:
        ipv6_config = convert_option212(read_leasefile(args.interface))
        if ipv6_config:
            print('6RD Prefix:              ' + ipv6_config['sixrd_prefix'])
            print('6RD Border Relay:        ' + ipv6_config['sixrd_border_relay'])
            print('6RD IPv4 Prefix length:  ' + str(ipv6_config['sixrd_ip4_prefix_len']) + ' bits')
            print('Delegation Prefix:       ' + '/' + str(ipv6_config['delegation_prefix']))
