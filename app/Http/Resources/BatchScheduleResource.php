<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BatchSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BatchSchedule
 */
class BatchScheduleResource extends JsonResource
{
    private const array DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'day_of_week' => $this->day_of_week,
            'day' => self::DAY_NAMES[$this->day_of_week] ?? 'Unknown',
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
        ];
    }
}
