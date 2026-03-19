# OPNsense auto 6rd configuration

Lightweight utilities to extract and expose 6RD (IPv6 Rapid Deployment)
configuration from DHCPv4 leases on OPNsense systems.

## Contents
- `root/configure_6rd.php` — optional PHP helper/installer
- `usr/local/etc/dhclient-exit-hooks.d/6rd_update` — dhclient hook example
 

## Requirements
- OPNsense with shell/SSH access
- DHCP client hooks enabled (`dhclient-script`)

## Installation

1. Copy the repository files to your OPNsense host
2. Ensure `configure_6rd.php` is placed in `/root` and executable if you want to run it manually.
3. If not existing create `/usr/local/etc/dhclient-exit-hooks.d` and place `6rd_update` there
4. Make both scripts executable using `chmod +x /root/configure_6rd.php /usr/local/etc/dhclient-exit-hooks.d/6rd_update`

## Manual execution and testing

Be carefuly and access the OPNsense firewall over an IPv4 connection from LAN interface, because if something went wrong you are able to repair. Call the PHP helper manually to configure and verify output:

```bash
/root/configure_6rd.php
```

## Integration

The `configure_6rd.php` is integrated into the OPNsense by the Hook `6rd_update` for the DHCP client. It will be called on every lease renew to update the IPv6 information on the WAN interface. But it will only do something, if WAN interface IPv6 Configuration Type is set to  **6rd Tunnel** and any configuration value has changed.

License
This project is licensed under the BSD 2-Clause License. See `LICENSE`.
