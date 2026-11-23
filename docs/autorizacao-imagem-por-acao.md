# Autorização de imagem por ação pedagógica

A decisão pertence ao participante e à ação (`Evento`), cobrindo todos os momentos (`Atividade`) dessa ação. O campo global legado permanece independente.

## Uso

Na página pública de presença, ao preencher CPF, e-mail ou telefone, a tela consulta se falta responder sobre o uso de imagem naquela ação. Quando falta, exibe as opções Sim/Não abaixo do identificador, sem escolha prévia. O botão de confirmar fica bloqueado até a escolha. O link do termo abre um modal apenas para leitura do texto atual da configuração. A obrigatoriedade é validada somente no frontend, sem novas tabelas ou migrations. A resposta enviada é registrada na estrutura existente, junto ao fluxo de presença, incluindo o passo de dados demográficos quando necessário.

O check-in autenticado mantém seu fluxo anterior de coleta após a presença.

Aceite e recusa evitam novas perguntas na mesma ação. No menu do perfil, **Minhas autorizações de imagem** permite consultar e alterar a própria resposta. A gestão está no menu do perfil, no gerenciamento de usuários e no dropdown **Gerenciar** da ação pedagógica.

O termo é somente o texto de `config/autorizacao_imagem.php`, chave `texto`. Pode ser editado diretamente, sem cadastrar versões, hashes ou documentar alterações. A gestão não solicita referência documental nem confirmação de documento.

## Dados e segurança

- Uma linha em `autorizacoes_imagem` por participante e ação, com unicidade no banco.
- Ausência de linha: **Não respondida**; `false`: **Não autorizada**; `true`: **Autorizada**.
- Data, origem e responsável são registrados automaticamente. O histórico automático guarda as mudanças de decisão.
- O contador interno `versao` protege contra edições concorrentes da decisão; não representa uma versão do termo.
- Os contextos existentes de perfil, gestão e coleta após check-in autenticado continuam vinculados à sessão, participante, ação, origem e, quando aplicável, presença, com expiração de 15 minutos. A consulta do formulário público apenas lê a pendência; não cria registros.
- No fluxo público, a identificação permanece por CPF, e-mail ou telefone, sem OTP ou login obrigatório. Esses dados não comprovam a identidade. Identificadores ambíguos são rejeitados.
- As permissões são `autorizacao-imagem.ver`, `.registrar` e `.exportar`. Usuários do Cartas não acessam essas rotas.

## Exportação

A planilha por ação diferencia pendência de recusa e exporta células como texto para preservar CPFs e evitar fórmulas. Importar presenças não cria autorização.

## Migrações e verificação

A migração inicial já cria somente as autorizações e o histórico de respostas, sem catálogo de termos nem referências documentais. A segunda migração configura as permissões.

```powershell
php vendor/bin/phpunit tests/Feature/AutorizacaoImagemServiceTest.php tests/Feature/AutorizacaoImagemFluxoTest.php tests/Feature/AutorizacaoImagemDocumentosTest.php
$env:AUTORIZACAO_IMAGEM_QA_DIR = Join-Path $env:TEMP 'engaja-autorizacao-qa'
php vendor/bin/phpunit tests/Feature/AutorizacaoImagemVisualTest.php
```

O teste visual usa Puppeteer e Chrome local com dados fictícios e requisições interceptadas. Configure `AUTORIZACAO_IMAGEM_CHROME` em outros ambientes. Exige assets compilados em `public/build`.
