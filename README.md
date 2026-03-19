# OPNsense auto 6rd configuration

Lightweight utilities to extract and expose 6RD (IPv6 Rapid Deployment)
configuration from DHCPv4 leases on OPNsense systems.

## Contents
- `configure_6rd.php` — optional PHP helper/installer
- `dhclient-script.patch` — dhclient-script patch
 

## Requirements
- OPNsense with shell/SSH access

## Installation

1. Copy the repository files to your OPNsense host
2. Ensure `configure_6rd.php` and `dhclient-script.patch` are placed in `/root` and the php script is executable if you want to run it manually.

## Manual execution and testing

Be carefuly and access the OPNsense firewall over an IPv4 connection from LAN interface, because if something went wrong you are able to repair. Call the PHP helper manually to configure and verify output:

```bash
/root/configure_6rd.php
```

## Integration

To integrate it for automatic execution on IP configuration changes, the `/usr/local/opnsense/scripts/interfaces/dhclient-script` has to be patched using the `dhclient-script.patch`. The patch can be applied using the following command:

```bash
patch /usr/local/opnsense/scripts/interfaces/dhclient-script < /root/dhclient-script.patch
```

After OPNsense updates check if the patch has to be re-applied. To reverse the patch execute the following command:

```bash
patch -R /usr/local/opnsense/scripts/interfaces/dhclient-script < /root/dhclient-script.patch
```


## License

This project is licensed under the BSD 2-Clause License. See `LICENSE`.
