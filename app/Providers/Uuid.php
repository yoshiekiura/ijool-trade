<?php

namespace App\Providers;

use Illuminate\Support\Str;

trait Uuid
{
  /**
   * Boot function from Laravel.
   */
  protected static function boot(): void
  {
    parent::boot();
    static::creating(function ($model) {
      if (empty($model->{$model->getKeyName()})) {
        $model->{$model->getKeyName()} = Str::uuid();
      }
    });
  }

  /**
   * Get the value indicating whether the IDs are incrementing.
   *
   * @return bool
   */
  public function getIncrementing(): bool
  {
    return false;
  }

  /**
   * Get the auto-incrementing key type.
   *
   * @return string
   */
  public function getKeyType(): string
  {
    return 'string';
  }
}
