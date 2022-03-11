# opnsense6rd

Scripts for extracting 6RD information from DHCP IPv4 lease on OPNsense

1. copy to your OPNsense  (in example `/root` is used)
2. execute script with wan (`re0`) interface as argument, for example:

## Manual calling

~~~bash
/root/6rd.py re0
~~~

## Integrate into dhclient-script

In `/usr/local/opnsense/scripts/interfaces/dhclient-script` modify the case loop as here:

~~~bash
BOUND|RENEW|REBIND|REBOOT)
        check_hostname
        changes="no"
        $LOGGER "6RD configuration: $(/root/6rd.py -e)"
        if [ -n "$old_ip_address" ]; then
~~~

or

~~~bash
BOUND|RENEW|REBIND|REBOOT)
        check_hostname
        changes="no"
        $LOGGER "6RD configuration: $(/root/6rd.php)"
        if [ -n "$old_ip_address" ]; then
~~~

The output of the 6RD configuration should be visible under System > Log Files > General.

## Using it with Monit to track changes

**Requirement:** Configured and working Monit service on OPNsense!

Create a Monit Service with following fields:

| Field | Content |
|-------|---------|
| Name  | 6rd_change |
| Type  | Custom     |
| Path  | /root/6rd.py re0 |
| Tests | ChangedStatus |
| Description | Check for changes of 6RD configuration |

Please change `re0` to your WAN interface. Regarding your configuration, the status can checked via *Services > Monit > Status* or on Dashboard using the Monit widget. If you have mail configured, any status changes will sending a mail, containing the 6RD configuration.
