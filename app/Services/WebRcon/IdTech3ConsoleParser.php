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
        $normalized = array_map(fn (string $rawLine): string => $this->stripAnsi(trim($rawLine)), $lines);
        $lastHeaderIndex = $this->findLastStatusHeaderIndex($normalized);

        $hostname = null;
        $map = null;
        $maxClients = null;
        $players = [];

        if ($lastHeaderIndex === null) {
            return [
                'hostname' => null,
                'map' => null,
                'max_clients' => null,
                'players' => [],
            ];
        }

        $metadataStart = max(0, $lastHeaderIndex - 40);
        for ($i = $metadataStart; $i < $lastHeaderIndex; $i++) {
            $line = $normalized[$i];
            if ($line === '' || $this->isStatusHeaderLine($line)) {
                continue;
            }

            if ($hostname === null && preg_match('/^hostname\s*:\s*(.+)$/i', $line, $matches)) {
                $hostname = trim($matches[1], " \t\"'");
            }

            if ($map === null && preg_match('/^map\s*:\s*(\S+)/i', $line, $matches)) {
                $map = trim($matches[1], " \t\"'");
            }

            if ($maxClients === null && preg_match('/^(?:g_maxclients|sv_maxclients)\s*:\s*(\d+)/i', $line, $matches)) {
                $maxClients = (int) $matches[1];
            }
        }

        $playersBySlot = [];
        for ($i = $lastHeaderIndex + 1; $i < count($normalized); $i++) {
            $line = $normalized[$i];
            if ($line === '') {
                continue;
            }

            if ($this->isStatusHeaderLine($line)) {
                break;
            }

            if (preg_match('/^---\s+-----/', $line)) {
                continue;
            }

            if (preg_match('/^(hostname|map|gametype|g_|sv_)/i', $line)) {
                break;
            }

            $player = $this->parsePlayerLine($line);
            if ($player === null) {
                continue;
            }

            $playersBySlot[$player['clientnum']] = $player;
        }

        ksort($playersBySlot);

        return [
            'hostname' => $hostname,
            'map' => $map,
            'max_clients' => $maxClients,
            'players' => array_values($playersBySlot),
        ];
    }

    /**
     * @param string[] $lines
     */
    private function findLastStatusHeaderIndex(array $lines): ?int
    {
        $lastIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^num\s+score\s+ping/i', $line)) {
                $lastIndex = $index;
            }
        }

        return $lastIndex;
    }

    private function isStatusHeaderLine(string $line): bool
    {
        return (bool) preg_match('/^num\s+score\s+ping/i', $line);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePlayerLine(string $line): ?array
    {
        if (!preg_match(
            '/^\s*(\d+)\s+(-?\d+)\s+(\d+)\s+(\S+)\s+(.*?)\s+(\d+)\s+(\d+\.\d+\.\d+\.\d+:\d+)/',
            $line,
            $matches
        )) {
            return null;
        }

        $name = trim($matches[5]);
        $guid = $matches[4];

        if (preg_match('/^[0-9a-f]{32}$/i', $guid)) {
            $guid = strtolower($guid);
        }

        return [
            'clientnum' => (int) $matches[1],
            'score' => (int) $matches[2],
            'ping' => (int) $matches[3],
            'guid' => $guid,
            'name' => $name,
            'address' => $matches[7],
        ];
    }

    private function stripAnsi(string $line): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?? $line;
    }
}
