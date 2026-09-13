<?php

namespace App\Models;

use App\Enums\PrintJobStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $login
 * @property string $password
 * @property bool $is_admin
 * @property bool $is_active
 * @property bool $must_change_password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PrintJob> $printJobs
 */
#[Fillable(['name', 'login', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * The print jobs submitted by this member.
     *
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * Total number of pages this member has actually printed.
     *
     * Le compteur est cumulé depuis la création du compte : il n'est jamais
     * remis à zéro, et ne compte que les tâches effectivement sorties de
     * l'imprimante — dépannages exclus, puisqu'ils remplacent une impression
     * qui n'a jamais abouti.
     */
    public function pagesPrinted(): int
    {
        return (int) $this->printJobs()
            ->where('status', PrintJobStatus::Printed)
            ->where('counts_pages', true)
            ->sum('pages_printed');
    }
}
