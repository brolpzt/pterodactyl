<?php
http_response_code(403);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>phpMyAdmin</title></head><body>';
echo '<p>Acesse o phpMyAdmin pelo painel HostGamer (link SSO na página de Databases).</p>';
echo '</body></html>';
