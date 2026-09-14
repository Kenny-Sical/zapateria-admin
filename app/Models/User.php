<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'wp_users';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'user_login',
        'user_pass',
        'user_nicename',
        'user_email',
        'user_url',
        'user_registered',
        'user_activation_key',
        'user_status',
        'display_name',
    ];

    protected $hidden = [
        'user_pass',
    ];

    /**
     * Devuelve la contraseña para el guard de autenticación.
     */
    public function getAuthPassword()
    {
        return $this->user_pass;
    }

    /**
     * Accesor para el ID en minúsculas.
     */
    public function getIdAttribute()
    {
        return $this->attributes['ID'] ?? null;
    }

    /**
     * Accesor para el nombre de usuario (compatibilidad con vistas existentes).
     */
    public function getNameAttribute(): string
    {
        return $this->display_name ?: $this->user_login;
    }

    /**
     * Accesor para el email (compatibilidad con vistas existentes).
     */
    public function getEmailAttribute(): string
    {
        return $this->user_email;
    }

    /**
     * Accesor para la fecha de registro.
     */
    public function getCreatedAtAttribute(): ?Carbon
    {
        if (!empty($this->user_registered) && $this->user_registered !== '0000-00-00 00:00:00') {
            return Carbon::parse($this->user_registered);
        }
        return null;
    }

    /**
     * Accesor para el rol (0: SuperAdmin/Administrator, 1: Editor/Shop Manager).
     */
    public function getRoleAttribute(): int
    {
        $cap = DB::table('wp_usermeta')
            ->where('user_id', $this->ID)
            ->where('meta_key', 'wp_capabilities')
            ->value('meta_value');

        if (!$cap) {
            return 1;
        }

        $unserialized = @unserialize($cap);
        if (is_array($unserialized) && !empty($unserialized['administrator'])) {
            return 0; // SuperAdmin
        }

        return 1; // Editor
    }

    /**
     * Guarda el rol en wp_usermeta.
     */
    public function setRole(int $role): void
    {
        $roleName = ($role === 0) ? 'administrator' : 'shop_manager';
        $capValue = serialize([$roleName => true]);
        $userLevel = ($role === 0) ? '10' : '7';

        DB::table('wp_usermeta')->updateOrInsert(
            ['user_id' => $this->ID, 'meta_key' => 'wp_capabilities'],
            ['meta_value' => $capValue]
        );

        DB::table('wp_usermeta')->updateOrInsert(
            ['user_id' => $this->ID, 'meta_key' => 'wp_user_level'],
            ['meta_value' => $userLevel]
        );
    }

    /**
     * Hashea una contraseña compatible con WordPress (WordPress 6.8+ HMAC-SHA384 + Bcrypt).
     */
    public static function hashPassword(string $password): string
    {
        $passwordToHash = base64_encode(hash_hmac('sha384', trim($password), 'wp-sha384', true));
        $bcryptHash = password_hash($passwordToHash, PASSWORD_BCRYPT, ['cost' => 10]);
        return '$wp' . $bcryptHash;
    }

    /**
     * Verifica una contraseña contra los formatos soportados por WordPress.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        if (strlen($password) > 4096) {
            return false;
        }

        // Formato WordPress 6.8+ con prefijo $wp (pre-hasheado con HMAC-SHA384)
        if (str_starts_with($hash, '$wp')) {
            $realHash = substr($hash, 3);
            $wpSha = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
            if (password_verify($wpSha, $realHash)) {
                return true;
            }
            // Fallback por si el hash fue creado con bcrypt directo y prefijo $wp
            if (password_verify($password, $realHash)) {
                return true;
            }
            return false;
        }

        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return password_verify($password, $hash);
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return self::checkPhpass($password, $hash);
        }

        if (hash('sha256', $password) === $hash) {
            return true;
        }

        if (md5($password) === $hash) {
            return true;
        }

        return false;
    }

    /**
     * Implementación ligera del verificador PHPass de WordPress.
     */
    private static function checkPhpass(string $password, string $storedHash): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if (strlen($storedHash) < 34) return false;

        $countLog2 = strpos($itoa64, $storedHash[3]);
        if ($countLog2 < 7 || $countLog2 > 30) return false;

        $count = 1 << $countLog2;
        $salt = substr($storedHash, 4, 8);
        if (strlen($salt) !== 8) return false;

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($storedHash, 0, 12);
        $i = 0;
        $len = 16;
        while ($i < $len) {
            $value = ord($hash[$i++]);
            $output .= $itoa64[$value & 0x3f];
            if ($i < $len) $value |= ord($hash[$i]) << 8;
            $output .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $len) break;
            if ($i < $len) $value |= ord($hash[$i]) << 16;
            $output .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $len) break;
            $output .= $itoa64[($value >> 18) & 0x3f];
        }

        return $output === $storedHash;
    }
}
