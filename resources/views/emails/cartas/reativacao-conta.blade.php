<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: 'Montserrat', Arial, sans-serif;
      background: #f5f3fa;
      padding: 0;
      margin: 0;
    }
    .wrapper {
      max-width: 620px;
      margin: 0 auto;
      padding: 32px 16px;
    }
    .logo-area {
      text-align: center;
      margin-bottom: 20px;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 32px 32px 28px;
      box-shadow: 0 14px 35px rgba(0,0,0,0.06);
    }
    .card-header {
      border-bottom: 1px solid #f0ece8;
      padding-bottom: 18px;
      margin-bottom: 22px;
    }
    h1 {
      font-size: 21px;
      color: #1f2937;
      margin: 0;
      font-weight: 700;
      line-height: 1.3;
    }
    p {
      color: #374151;
      font-size: 15px;
      line-height: 1.65;
      margin: 0 0 16px;
    }
    .btn {
      display: inline-block;
      background: #7c3aed;
      color: #fff !important;
      padding: 13px 28px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      margin: 8px 0 20px;
    }
    .muted {
      font-size: 13px;
      color: #6b7280;
      word-break: break-all;
      margin-top: 0;
    }
    .footer {
      text-align: center;
      margin-top: 24px;
      font-size: 12px;
      color: #9ca3af;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="logo-area">
      <img src="{{ asset('images/cartas/cartas-logo.png') }}" alt="Cartas para Esperançar" style="max-height:60px;width:auto;">
    </div>

    <div class="card">
      <div class="card-header">
        <h1>Reative sua conta</h1>
      </div>

      <p>Olá, {{ $userName }}.</p>

      <p>Encontramos uma conta desativada no <strong>Cartas para Esperançar</strong> com este e-mail. Para reativá-la com os novos dados informados no cadastro, confirme clicando no botão abaixo.</p>

      <p>
        <a class="btn" href="{{ $url }}">Reativar minha conta</a>
      </p>

      <p style="margin-top: 24px;">
         Obrigado por esperançar com a gente.<br>
         <strong>Projeto ALFA-EJA Brasil | Cartas para Esperançar</strong>
      </p>
      <p class="muted">Se o botão não funcionar, copie e cole este link no navegador:<br>{{ $url }}</p>
      <p class="muted">Se você não solicitou isso, ignore este e-mail — nada será alterado.</p>
    </div>

    <div class="footer">
      Projeto ALFA-EJA Brasil · Cartas para Esperançar
    </div>
  </div>
</body>
</html>
