#!/usr/local/bin/php
<?php
/*
 script to convert option 212 string to 6RD config
*/

require_once("IPv6.inc");

/*
 convert options-212 string into 6RD configuration for OPNsense
 :param value_string
 :return: dictionary
*/
function convert_option212($value_string)
{
    // not sure if values always separated by ':'
    $option_array = explode(":", $value_string);
    $option_length = count($option_array);
    if ($option_length < 22)
        return null;

    // IPv4MaskLen   The number of high-order bits that are identical
    //               across all CE IPv4 addresses within a given 6rd
    //               domain.  This may be any value between 0 and 32.
    //               Any value greater than 32 is invalid.
    $sixrd_ip4_prefix_len = hexdec("0x".$option_array[0]);
    if ($sixrd_ip4_prefix_len > 32)
        return null;

    // 6rdPrefixLen  The IPv6 prefix length of the SP's 6rd IPv6
    //               prefix in number of bits.  For the purpose of
    //               bounds checking by DHCP option processing, the
    //               sum of (32 - IPv4MaskLen) + 6rdPrefixLen MUST be
    //               less than or equal to 128.
    $sixrd_prefix_len = hexdec("0x".$option_array[1]);
    $delegation_prefix = 32 - $sixrd_ip4_prefix_len + $sixrd_prefix_len;
    if ($delegation_prefix > 128)
        return null;

    // 6rdPrefix  The service provider's 6rd IPv6 prefix
    //            represented as a 16-octet IPv6 address.  The bits
    //            in the prefix after the 6rdPrefixlen number of
    //            bits are reserved and MUST be initialized to zero
    //            by the sender and ignored by the receiver.
    $sixrd_prefix = "";
    foreach (array(2, 4, 6, 8, 10, 12, 14, 16) as &$index) {
        $index2 = $index + 1;
        $sixrd_prefix .= sprintf("%02s", $option_array[$index]).sprintf("%02s", $option_array[$index2]).":";
    }
    $sixrd_prefix = Net_IPv6::compress(rtrim($sixrd_prefix, ":"));

    // 6rdBRIPv4Address  One or more IPv4 addresses of the 6rd Border
    //                   Relay(s) for a given 6rd domain.
    $sixrd_border_relay = "";
    $index = 18;
    while ($index < $option_length) {
        $sixrd_border_relay = hexdec("0x".$option_array[$index]) . "." . hexdec("0x".$option_array[$index + 1]) . "." . hexdec("0x".$option_array[$index + 2]) . "." . hexdec("0x".$option_array[$index + 3]);
        $index += 4;
    }

    $sixrd_cfg['prefix-6rd-v4plen'] = $sixrd_ip4_prefix_len;
    $sixrd_cfg['prefix-6rd'] = $sixrd_prefix . "/" . $sixrd_prefix_len;
    $sixrd_cfg['prefix-6rd-v4addr'] = $sixrd_border_relay;
    $sixrd_cfg['delegation_prefix'] = $delegation_prefix;
    return ($sixrd_cfg);
}

// get option 212 from environment variables
$old_option212 = getenv('old_option_212');
$new_option212 = getenv('new_option_212');

if ($old_option212 != $new_option212) {
    $rd6_cfg = convert_option212($new_option212);
}
else {
    echo "No changes.\n";
    $rd6_cfg = null;
}

if ($rd6_cfg) {
    echo "6RD Prefix:              " . $rd6_cfg['prefix-6rd'] . "\n";
    echo "6RD Border Relay:        " . $rd6_cfg['prefix-6rd-v4addr'] . "\n";
    echo "6RD IPv4 Prefix length:  " . $rd6_cfg['prefix-6rd-v4plen'] . ' bits' . "\n";
    echo "Delegation Prefix:       /" . $rd6_cfg['delegation_prefix'] . "\n";
}
