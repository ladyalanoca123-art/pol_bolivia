<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Notifications\RestablecerContrasenaNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Verificación de correo DESACTIVADA temporalmente (se reactivará al final del proyecto).
// Para reactivarla: volver a declarar "class User extends Authenticatable implements MustVerifyEmail".
// Mientras tanto, el middleware 'verified' de las rutas deja pasar a todos los usuarios.
class User extends Authenticatable
{
    use HasFactory, Notifiable, RegistraAuditoria;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombres', 'apellidos', 'email', 'telefono',
        'password',
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
            // Administrador principal (una sola cuenta): lo protege PostgreSQL (trigger_proteger_administrador_principal).
            'es_principal' => 'boolean',
        ];
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /** Enlace para restablecer la contraseña, en español (reemplaza a la notificación de Laravel). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasenaNotification($token));
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
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

    /** Constancias de aceptación de los Términos y la Política de privacidad (tabla aceptaciones_legales). */
    public function aceptacionesLegales(): HasMany
    {
        return $this->hasMany(AceptacionLegal::class, 'usuario_id');
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
