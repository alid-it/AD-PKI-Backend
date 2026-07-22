<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = [
        'type',
        'common_name',
        'san',
        'serial_number',
        'valid_from',
        'valid_to',
        'parent_id',
        'is_acme',
        'crt_path',
        'key_path',
        'key_passphrase',
        'chain_path',
        'crl_path',
        'revoked',
        'revoked_at',
        'revocation_reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'request_data',
        'key_type',
        'key_size',
        'curve',
        'team_id',
        'acme_account_id',
    ];

    protected $casts = [
        'is_acme'      => 'boolean',
        'revoked'      => 'boolean',
        'valid_from'   => 'datetime',
        'valid_to'     => 'datetime',
        'revoked_at'   => 'datetime',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'request_data' => 'array',
        'key_passphrase' => 'encrypted',
    ];

    // Passphrase darf niemals in API-Responses auftauchen.
    protected $hidden = [
        'key_passphrase',
    ];

    public function parent()
    {
        return $this->belongsTo(Certificate::class, 'parent_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // 🔥 NEU
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
