<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombres', 'apellidos', 'correo', 'telefono',
        'password', 'rol', 'estado',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado' => 'boolean',
        ];
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function mascotasRegistradas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'registrado_por');
    }

    public function solicitudesAdopcion(): HasMany
    {
        return $this->hasMany(SolicitudAdopcion::class, 'solicitante_id');
    }

    public function seguimientosAsignados(): HasMany
    {
        return $this->hasMany(SeguimientoAdopcion::class, 'voluntario_id');
    }

    public function visitasSeguimiento(): HasMany
    {
        return $this->hasMany(VisitaSeguimiento::class, 'voluntario_id');
    }

    public function incidenciasReportadas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'reportado_por');
    }

    public function incidenciasAtendidas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'atendida_por');
    }

    public function postulacionesVoluntariado(): HasMany
    {
        return $this->hasMany(PostulacionVoluntariado::class, 'usuario_id');
    }

    public function postulacionesRevisadas(): HasMany
    {
        return $this->hasMany(PostulacionVoluntariado::class, 'revisado_por');
    }

    public function perfilVoluntario(): HasOne
    {
        return $this->hasOne(Voluntario::class, 'usuario_id');
    }
}