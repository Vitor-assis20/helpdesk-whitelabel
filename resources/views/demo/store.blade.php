{{--
    Site fictício de uma empresa cliente, com o widget instalado.
    Fica no domínio principal: o widget é carregado de outra origem
    ({empresa}.{dominio}), como num site real (CORS, sem cookies).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} · Loja online (demonstração)</title>
    <style>
        body { margin: 0; font-family: Georgia, 'Times New Roman', serif; color: #1f2937; background: #fffaf3; }
        header { padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eadfcf; }
        header strong { font-size: 22px; letter-spacing: .5px; }
        nav a { margin-left: 24px; color: #1f2937; text-decoration: none; }
        .hero { padding: 64px 40px; text-align: center; }
        .hero h1 { font-size: 40px; margin: 0 0 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; padding: 0 40px 64px; max-width: 1000px; margin: 0 auto; }
        .product { background: #fff; border: 1px solid #eadfcf; border-radius: 8px; padding: 16px; }
        .product .photo { height: 140px; border-radius: 6px; background: linear-gradient(135deg, #f3e8d8, #e9d5ff); margin-bottom: 12px; }
        .notice { margin: 0 auto 40px; max-width: 640px; padding: 12px 16px; border: 1px dashed #c4b5fd; border-radius: 8px; font-family: ui-monospace, monospace; font-size: 13px; background: #faf5ff; }
    </style>
</head>
<body>
    <header>
        <strong>{{ $tenant->name }}</strong>
        <nav><a href="#">Coleção</a><a href="#">Ofertas</a><a href="#">Contato</a></nav>
    </header>
    <section class="hero">
        <h1>Coleção de outono</h1>
        <p>Peças leves para os dias amenos. Dúvidas? Fale com a gente no canto da tela.</p>
    </section>
    <div class="grid">
        @foreach (['Camiseta básica · R$ 79', 'Jaqueta jeans · R$ 249', 'Tênis casual · R$ 319', 'Mochila urbana · R$ 189'] as $product)
            <div class="product"><div class="photo"></div>{{ $product }}</div>
        @endforeach
    </div>
    <p class="notice">Página de demonstração. O widget foi instalado com uma linha:<br>&lt;script src="{{ $scriptUrl }}" async&gt;&lt;/script&gt;</p>

    <script src="{{ $scriptUrl }}" async></script>
</body>
</html>
