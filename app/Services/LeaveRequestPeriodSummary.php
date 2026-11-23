<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestHistory;

class LeaveRequestPeriodSummary
{
    /**
     * @return array{submitted: int, processed: int, period: string}
     */
    public function currentQuarter(): array
    {
        $periodStart = now()->startOfQuarter();
        $periodEnd = $periodStart->copy()->endOfQuarter();
        $quarterNames = ['I', 'II', 'III', 'IV'];
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return [
            'submitted' => DocumentRequest::query()
                ->where('type', 'cuti')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count(),
            'processed' => DocumentRequestHistory::query()
                ->whereHas('documentRequest', fn ($query) => $query->where('type', 'cuti'))
                ->whereIn('action', ['disetujui', 'ditolak', 'perlu_revisi'])
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->distinct()
                ->count('document_request_id'),
            'period' => sprintf(
                'Triwulan %s %s (%s–%s)',
                $quarterNames[$periodStart->quarter - 1],
                $periodStart->format('Y'),
                $monthNames[$periodStart->month],
                $monthNames[$periodEnd->month],
            ),
        ];
    }
}
