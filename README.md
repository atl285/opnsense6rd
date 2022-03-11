# opnsense6rd
script for extracting 6RD information from DHCP IPv4 lease on OPNsense

1. copy to your OPNsense
2. execute script with wan interface as argument, for example:

~~~
./6rd.py re0
~~~~

2nd method:

in `/usr/local/opnsense/scripts/interfaces/dhclient-script` modify the case loop as here:

~~~
BOUND|RENEW|REBIND|REBOOT)
        check_hostname
        changes="no"
        $LOGGER "6RD configuration: $(/root/6rd.py -e)"
        if [ -n "$old_ip_address" ]; then
~~~

or

~~~
BOUND|RENEW|REBIND|REBOOT)
        check_hostname
        changes="no"
        $LOGGER "6RD configuration: $(/usr/local/bin/php /root/6rd.php)"
        if [ -n "$old_ip_address" ]; then
~~~

Then the output of the 6RD configuration should displayed under System > Log Files > General.