<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantBranding extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'tenant_branding';

    protected $fillable = [
        'logo_path', 'favicon_path', 'hero_image_path', 'primary_color', 'secondary_color', 'accent_color',
        'font_family', 'homepage_json', 'contact_json', 'social_json', 'email_sender_name',
        'pwa_name', 'pwa_theme_color', 'pwa_icon_path',
    ];

    protected $casts = [
        'homepage_json' => 'array',
        'contact_json' => 'array',
        'social_json' => 'array',
    ];
}
