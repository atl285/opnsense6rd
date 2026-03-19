#!/usr/local/bin/php
<?php

require_once("config.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("system.inc");

$log_tag = "6RD-CONFIG";

// Function to log messages to both console and system log
function sixrd_log($msg) {
    global $log_tag;
    echo "$log_tag: $msg\n";
    log_error("$log_tag: $msg");
}

// Function to process a single interface
function process_6rd_interface($config, $logical_if) {
    global $log_tag;
    
    // 1. Check if interface exists and uses 6rd
    if (!isset($config['interfaces'][$logical_if])) {
        sixrd_log("Interface '$logical_if' not found in config.xml.");
        return false;
    }
    
    if (($config['interfaces'][$logical_if]['ipaddrv6'] ?? '') !== '6rd') {
        sixrd_log("Interface '$logical_if' does not use '6rd' IPv6 type, skipping.");
        return false;
    }
    
    // 2. Extract physical interface
    if (!isset($config['interfaces'][$logical_if]['if'])) {
        sixrd_log("Error: Physical interface not found for '$logical_if'.");
        return false;
    }
    
    $phys_if = $config['interfaces'][$logical_if]['if'];
    sixrd_log("Processing logical interface '$logical_if' (physical: $phys_if).");
    
    // 3. Determine and check lease file
    $lease_file = "/var/db/dhclient.leases.{$phys_if}";
    if (!file_exists($lease_file)) {
        sixrd_log("Warning: Lease file $lease_file not found for '$logical_if'.");
        return false;
    }
    
    // 4. Extract Option 212
    $content = file_get_contents($lease_file);
    preg_match_all('/option-212\s+([^;]+);/', $content, $matches);
    
    if (empty($matches[1])) {
        sixrd_log("No Option 212 found in lease for '$logical_if'.");
        return false;
    }
    
    // $matches[1] contains all found hex strings. We take the last (most recent) one.
    $raw_hex = trim(end($matches[1]), '" ');
    $parts = explode(':', $raw_hex);
    
    if (count($parts) < 22) {
        sixrd_log("Error: Hex string too short in Option 212 for '$logical_if'.");
        return false;
    }
    
    // 5. Parse values from Option 212
    $v4mask = hexdec($parts[0]);
    $v6len  = hexdec($parts[1]);
    
    $v6bin = "";
    for ($i = 2; $i < 18; $i++) {
        $v6bin .= pack("H*", str_pad($parts[$i], 2, "0", STR_PAD_LEFT));
    }
    $v6prefix = inet_ntop($v6bin);
    
    $br_parts = [];
    for ($i = 18; $i < 22; $i++) {
        $br_parts[] = hexdec($parts[$i]);
    }
    $br_ip = implode('.', $br_parts);
    
    sixrd_log("Data extracted from Option 212: Prefix=$v6prefix/$v6len, Relay=$br_ip, V4Mask=$v4mask");
    
    // 6. Compare with current configuration and update if needed
    $changed = false;
    $update_fields = [
        'prefix-6rd' => "$v6prefix/$v6len",
        'gateway-6rd' => $br_ip,
        'prefix-6rd-v4plen' => (string)$v4mask
    ];
    
    foreach ($update_fields as $key => $val) {
        $current = $config['interfaces'][$logical_if][$key] ?? '';
        if ($current !== $val) {
            sixrd_log("Updating '$logical_if': $key from '$current' to '$val'.");
            $config['interfaces'][$logical_if][$key] = $val;
            $changed = true;
        }
    }
    
    if ($changed) {
        write_config("6RD auto-update via script for $logical_if ($phys_if)");
        configd_run("interface reconfigure $logical_if");
        sixrd_log("Configuration updated and $logical_if ($phys_if) restarted.");
        return true;  // Signal that config was changed
    } else {
        sixrd_log("No changes needed for interface '$logical_if'.");
        return false;
    }
}

// 1. Load configuration
global $config;
$config = config_read_array();

// 2. Find all interfaces configured to use 6rd
sixrd_log("Scanning for interfaces configured with 6rd...");

if (!isset($config['interfaces']) || !is_array($config['interfaces'])) {
    sixrd_log("Error: No interfaces found in configuration.");
    exit(1);
}

foreach ($config['interfaces'] as $logical_if => $iface_cfg) {
    if (($iface_cfg['ipaddrv6'] ?? '') === '6rd') {
        process_6rd_interface($config, $logical_if);
    }
}

