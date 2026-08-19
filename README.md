# Feitosa Ribas — Holding Familiar

Landing page do escritório Feitosa Ribas Advocacia, voltada a holding familiar,
planejamento sucessório e proteção patrimonial.

HTML5, CSS3 e JavaScript puro. Sem frameworks, sem build, sem dependências.
O envio do formulário usa PHP no servidor.

## Arquivos

| Arquivo | O que faz |
|---|---|
| `index.html` | Estrutura da página |
| `estilos.css` | Todo o visual — variáveis, grid, clamp, media queries |
| `script.js` | Interações — menu, carrosséis, formulário, modal |
| `enviar.php` | Recebe o formulário e envia o e-mail por SMTP |
| `diagnostico.php` | Testa a configuração de e-mail. **Apague do servidor depois de usar** |
| `imagens/` | Logo, favicon, foto do sócio e fotos do escritório |
| `LEIA-ME.txt` | Manual completo: publicação, configuração e manutenção |

## Publicação

Envie todos os arquivos para `public_html/` numa hospedagem com PHP.

O formulário **não funciona** abrindo o arquivo localmente nem pelo Live Server
do VS Code — nenhum dos dois executa PHP.

## Credenciais

> **A senha do e-mail não está neste repositório, e não deve estar.**

O campo `smtp_senha` em `enviar.php` fica propositalmente vazio. Ele é
preenchido direto no servidor, pelo Gerenciador de Arquivos da hospedagem.

Se você editar esse arquivo na sua máquina, **apague a senha antes de fazer
commit**. Uma senha enviada ao Git permanece no histórico mesmo que seja
removida depois.

## Cache do navegador

Ao alterar `estilos.css` ou `script.js`, incremente o número de versão nas duas
referências dentro do `index.html`:

```html
<link rel="stylesheet" href="estilos.css?v=11">
<script src="script.js?v=11" defer></script>
```

Sem isso, quem já visitou o site continua recebendo a versão guardada no
navegador.

## Detalhes

O `LEIA-ME.txt` traz o passo a passo de publicação, a configuração do e-mail,
como editar os depoimentos e o checklist antes de divulgar.
