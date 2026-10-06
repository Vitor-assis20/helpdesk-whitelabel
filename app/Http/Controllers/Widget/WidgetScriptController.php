<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Support\BrandColor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Response;

/**
 * O script que a empresa cola no próprio site:
 * <script src="https://{empresa}.{dominio}/api/widget.js" async></script>
 *
 * Devolve a configuração da empresa (nome, cor, endereços da API e do
 * WebSocket) seguida do bundle do widget, compilado pelo Vite.
 */
class WidgetScriptController extends Controller
{
    public function __invoke(CurrentTenant $current): Response
    {
        $tenant = $current->get();
        $bundle = public_path('widget/widget.js');
        abort_unless(is_file($bundle), 503, 'Widget ainda não compilado: rode "npm run build".');

        $color = $tenant->publicBranding()['primary_color'] ?? '#18181b';
        $config = [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'color' => $color,
            'textColor' => BrandColor::readableText($color),
            'apiUrl' => url('/api/widget'),
            'reverb' => config('services.reverb_public'),
        ];

        $script = 'window.__HELPDESK_WIDGET__='.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).";\n".file_get_contents($bundle);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Curto: a cor ou o nome da empresa podem mudar.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
