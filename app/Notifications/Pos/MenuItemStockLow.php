<?php

namespace App\Notifications\Pos;

use App\Models\PosMenuItem;
use App\Notifications\TeamNotification;

class MenuItemStockLow extends TeamNotification
{
    /**
     * @param  'low'|'out'  $level
     */
    public function __construct(public PosMenuItem $item, public string $level)
    {
        //
    }

    /**
     * @return array{team_id: int, message: string, url: string, pos_menu_item_id: int, level: string}
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->level === 'out'
            ? "{$this->item->name} is out of stock."
            : "{$this->item->name} is running low ({$this->item->stock_quantity} left).";

        return [
            'team_id' => $this->item->team_id,
            'message' => $message,
            'url' => route('pos.reports', [$this->item->team->slug, $this->item->pos_outlet_id]),
            'pos_menu_item_id' => $this->item->id,
            'level' => $this->level,
        ];
    }
}
