<?php

namespace App\Domains\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Database notifications store translation keys + params (`title`,
 * `message`, `params`, `date_params`) rather than translated text, so
 * they are rendered here in the reader's current language — not frozen
 * in the language of whoever triggered them. Rows without `params`
 * (older format) are shown as stored.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => self::translate($this->data),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function translate(array $data): array
    {
        if (! array_key_exists('params', $data)) {
            return $data;
        }

        /** @var array<string, string> $params */
        $params = $data['params'];

        foreach ($data['date_params'] ?? [] as $name) {
            if (isset($params[$name])) {
                // parse() keeps the stored offset: the time stays the
                // center's local time, only the wording follows the locale.
                $params[$name] = Carbon::parse($params[$name])->translatedFormat(__('formats.datetime'));
            }
        }

        $data['title'] = __((string) $data['title'], $params);
        $data['message'] = __((string) $data['message'], $params);
        unset($data['params'], $data['date_params']);

        return $data;
    }
}
