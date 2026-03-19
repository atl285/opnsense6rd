#!/usr/local/bin/php
<?php

require_once("config.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("system.inc");

$log_tag = "6RD-CONFIG";
$logical_if = "wan"; 

// Funktion umbenannt, um Konflikt mit OPNsense-Core zu vermeiden
function sixrd_log($msg) {
    global $log_tag;
    echo "$log_tag: $msg\n";
    log_error("$log_tag: $msg");
}

// 1. Konfiguration laden
global $config;
$config = config_read_array();

// 2. Physisches Interface aus <interfaces><wan><if> extrahieren
if (!isset($config['interfaces'][$logical_if]['if'])) {
    sixrd_log("Abbruch: Interface '$logical_if' nicht in config.xml gefunden.");
    exit(1);
}

$phys_if = $config['interfaces'][$logical_if]['if']; 
sixrd_log("Physisches Interface fuer $logical_if ist $phys_if.");

// 3. Prüfen, ob 6rd aktiv ist
if (($config['interfaces'][$logical_if]['ipaddrv6'] ?? '') !== '6rd') {
    sixrd_log("Abbruch: IPv6-Typ fuer $logical_if ist nicht '6rd'.");
    exit(0);
}

// 4. Lease-Datei dynamisch bestimmen
$lease_file = "/var/db/dhclient.leases.{$phys_if}";
if (!file_exists($lease_file)) {
    sixrd_log("Fehler: Lease-Datei $lease_file nicht gefunden.");
    exit(1);
}

// 5. Option 212 extrahieren
$content = file_get_contents($lease_file);
preg_match_all('/option-212\s+([^;]+);/', $content, $matches);

if (empty($matches[1])) {
    sixrd_log("Keine Option 212 im Lease gefunden.");
    exit(0);
}

// $matches[1] enthält alle gefundenen Hex-Strings. Wir nehmen den letzten (aktuellsten).
$raw_hex = trim(end($matches[1]), '" ');
$parts = explode(':', $raw_hex);

if (count($parts) < 22) {
    sixrd_log("Hex-String zu kurz.");
    exit(1);
}

// 6. Werte parsen
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

sixrd_log("Daten ermittelt: Prefix=$v6prefix/$v6len, Relay=$br_ip, V4Mask=$v4mask");

// 7. Vergleich und Update
$changed = false;
$update_fields = [
    'prefix-6rd' => "$v6prefix/$v6len",
    'gateway-6rd' => $br_ip,
    'prefix-6rd-v4plen' => (string)$v4mask,
    'mtu-6rd' => "1480"
];

foreach ($update_fields as $key => $val) {
    if (($config['interfaces'][$logical_if][$key] ?? '') !== $val) {
        $config['interfaces'][$logical_if][$key] = $val;
        $changed = true;
    }
}

if ($changed) {
    write_config("6RD Auto-Update via Script fuer $logical_if ($phys_if)");
    configd_run("interface reconfigure $logical_if");
    sixrd_log("Konfiguration inkl. MTU 1480 aktualisiert und $logical_if ($phys_if) neu gestartet.");
} else {
    sixrd_log("Werte unveraendert. Kein Update erforderlich.");
}
