<?php
/**
 * HostGamer phpMyAdmin — SSO via painel (signon).
 * Per-node instance: set PmaAbsoluteUri / PMA_ABSOLUTE_URI to this node's hostname.
 */
$cfg['Servers'][1]['auth_type'] = 'signon';
$cfg['Servers'][1]['SignonSession'] = 'SignonSession';
$cfg['Servers'][1]['SignonURL'] = 'login-required.php';
$cfg['Servers'][1]['LogoutURL'] = '';
$cfg['Servers'][1]['host'] = getenv('PMA_HOST') ?: '10.8.0.6';
$cfg['Servers'][1]['port'] = getenv('PMA_PORT') ?: '3306';
$cfg['Servers'][1]['AllowRoot'] = false;
$cfg['Servers'][1]['DisableIS'] = false;
$cfg['Servers'][1]['AllowNoPassword'] = false;
$cfg['ForceSSL'] = false;
$cfg['PmaAbsoluteUri'] = getenv('PMA_ABSOLUTE_URI') ?: 'https://phpmyadmin-node050.hostgamer.net/';
