<?php

final class KnnClassifier
{
    public function classify(array $feature, array $dataset, int $k): array
    {
        if ($k < 1) {
            throw new InvalidArgumentException('Nilai K minimal 1.');
        }

        if (count($dataset) < $k) {
            throw new InvalidArgumentException('Jumlah dataset latih lebih kecil dari nilai K.');
        }

        $neighbors = array_map(function (array $row) use ($feature): array {
            $distance = $this->euclideanDistance($feature, $row);
            return [
                'id_dataset' => $row['id_dataset'],
                'label_kelas' => $row['label_kelas'],
                'mean_red' => $row['mean_red'],
                'mean_green' => $row['mean_green'],
                'mean_blue' => $row['mean_blue'],
                'distance' => $distance,
            ];
        }, $dataset);

        usort($neighbors, fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        $nearest = array_slice($neighbors, 0, $k);
        $votes = [];
        $distanceTotals = [];

        foreach ($nearest as $neighbor) {
            $label = $neighbor['label_kelas'];
            $votes[$label] = ($votes[$label] ?? 0) + 1;
            $distanceTotals[$label] = ($distanceTotals[$label] ?? 0) + $neighbor['distance'];
        }

        uksort($votes, function (string $a, string $b) use ($votes, $distanceTotals): int {
            $voteCompare = $votes[$b] <=> $votes[$a];
            if ($voteCompare !== 0) {
                return $voteCompare;
            }

            return $distanceTotals[$a] <=> $distanceTotals[$b];
        });

        $prediction = array_key_first($votes);

        return [
            'kelas_prediksi' => $prediction,
            'jarak_euclidean' => round($distanceTotals[$prediction] / $votes[$prediction], 4),
            'jarak_terdekat' => round($nearest[0]['distance'], 4),
            'nearest_neighbors' => $nearest,
        ];
    }

    private function euclideanDistance(array $a, array $b): float
    {
        return sqrt(
            (($a['mean_red'] - $b['mean_red']) ** 2) +
            (($a['mean_green'] - $b['mean_green']) ** 2) +
            (($a['mean_blue'] - $b['mean_blue']) ** 2)
        );
    }
}
