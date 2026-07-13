<?php

namespace Pterodactyl\Services\WebRcon;

class IdTech3ConsoleParser
{
    /**
     * @param string[] $lines
     *
     * @return array{
     *     hostname: ?string,
     *     map: ?string,
     *     max_clients: ?int,
     *     players: array<int, array<string, mixed>>
     * }
     */
    public function parseStatus(array $lines): array
    {
        $hostname = null;
        $map = null;
        $maxClients = null;
        $players = [];
        $inPlayerSection = false;

        foreach ($lines as $rawLine) {
            $line = $this->stripAnsi(trim($rawLine));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^hostname\s*:\s*(.+)$/i', $line, $matches)) {
                $hostname = trim($matches[1], " \t\"'");
                continue;
            }

            if (preg_match('/^map\s*:\s*(\S+)/i', $line, $matches)) {
                $map = trim($matches[1], " \t\"'");
                continue;
            }

            if (preg_match('/^(?:g_maxclients|sv_maxclients)\s*:\s*(\d+)/i', $line, $matches)) {
                $maxClients = (int) $matches[1];
                continue;
            }

            if (preg_match('/^num\s+score\s+ping/i', $line) || preg_match('/^---\s+-----/', $line)) {
                $inPlayerSection = true;
                continue;
            }

            if (!$inPlayerSection) {
                continue;
            }

            $player = $this->parsePlayerLine($line);
            if ($player !== null) {
                $players[] = $player;
            }
        }

        return [
            'hostname' => $hostname,
            'map' => $map,
            'max_clients' => $maxClients,
            'players' => $players,
        ];
    }

    /**
     * @param string[] $lines
     */
    public function parseDvarValue(string $name, array $lines): ?string
    {
        foreach ($lines as $rawLine) {
            $line = $this->stripAnsi(trim($rawLine));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^"' . preg_quote($name, '/') . '"\s+is\s+"([^"]*)"$/i', $line, $matches)) {
                return $matches[1];
            }

            if (preg_match('/^' . preg_quote($name, '/') . '\s*=\s*(.+)$/i', $line, $matches)) {
                return trim($matches[1], " \t\"'");
            }

            if (preg_match('/^' . preg_quote($name, '/') . '\s+is\s+(.+)$/i', $line, $matches)) {
                return trim($matches[1], " \t\"'");
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePlayerLine(string $line): ?array
    {
        if (preg_match(
            '/^\s*(\d+)\s+(-?\d+)\s+(\d+)\s+([0-9a-fA-F]+)\s+(.+?)\s+(\d+)\s+(\d+\.\d+\.\d+\.\d+:\d+)/',
            $line,
            $matches
        )) {
            return [
                'clientnum' => (int) $matches[1],
                'score' => (int) $matches[2],
                'ping' => (int) $matches[3],
                'guid' => strtolower($matches[4]),
                'name' => trim($matches[5]),
                'address' => $matches[7],
            ];
        }

        if (preg_match(
            '/^\s*(\d+)\s+(-?\d+)\s+(\d+)\s+(.+?)\s+(\d+\.\d+\.\d+\.\d+:\d+)/',
            $line,
            $matches
        )) {
            return [
                'clientnum' => (int) $matches[1],
                'score' => (int) $matches[2],
                'ping' => (int) $matches[3],
                'guid' => null,
                'name' => trim($matches[4]),
                'address' => $matches[5],
            ];
        }

        return null;
    }

    private function stripAnsi(string $line): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?? $line;
    }
}
