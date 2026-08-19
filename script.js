/* ============================================================
   FEITOSA RIBAS — Landing Page Holding Familiar
   script.js · JavaScript puro (ES6), sem bibliotecas
   ============================================================ */

(function () {
  'use strict';

  /* ---------- 1. Menu móvel ---------- */
  const menuToggle = document.getElementById('menuToggle');
  const nav = document.getElementById('nav');

  if (menuToggle && nav) {
    menuToggle.addEventListener('click', function () {
      const aberto = nav.classList.toggle('aberto');
      menuToggle.setAttribute('aria-expanded', String(aberto));
      menuToggle.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    });

    // fecha ao clicar num link
    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('aberto');
        menuToggle.setAttribute('aria-expanded', 'false');
      });
    });

    // fecha com a tecla ESC
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('aberto')) {
        nav.classList.remove('aberto');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.focus();
      }
    });
  }

  /* ---------- 2. Sombra no cabeçalho ao rolar ---------- */
  const cabecalho = document.getElementById('cabecalho');
  const btnWhats = document.getElementById('waFlutuante');

  function aoRolar() {
    const y = window.scrollY;
    if (cabecalho) cabecalho.classList.toggle('rolado', y > 20);
    if (btnWhats) btnWhats.classList.toggle('visivel', y > 600);
  }
  window.addEventListener('scroll', aoRolar, { passive: true });
  aoRolar();


  /* ---------- 3. Revelar seções ao rolar ---------- */
  const alvos = document.querySelectorAll(
    '.secao__cabecalho, .celula, .depoimento, .passos li, .comparativo, .diagrama, .cartao-imagem, .lista-diferenciais, .formulario'
  );

  if ('IntersectionObserver' in window) {
    alvos.forEach(function (el) { el.classList.add('revelar'); });

    const observador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('visivel');
          observador.unobserve(entrada.target);
        }
      });
    }, { rootMargin: '0px 0px -60px 0px', threshold: 0.1 });

    alvos.forEach(function (el) { observador.observe(el); });
  }

  /* ---------- 4. Carrosséis (escritório e depoimentos) ---------- */
  document.querySelectorAll('.carrossel').forEach(function (carrossel) {
    const palco = carrossel.querySelector('.carrossel__palco');
    const trilha = carrossel.querySelector('.carrossel__trilha');
    const pontos = carrossel.querySelector('.carrossel__pontos');
    if (!palco || !trilha) return;

    const slides = Array.prototype.slice.call(trilha.querySelectorAll('.carrossel__slide'));
    const total = slides.length;
    if (!total) return;

    const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const intervalo = parseInt(carrossel.dataset.intervalo, 10) || 5000;

    let passo = 0;      // largura de um cartão + o vão
    let porVista = 1;   // quantos cartões aparecem juntos
    let maxIdx = 0;     // último deslocamento possível
    let atual = 0;
    let timer = null;

    // o CSS decide quantos cartões cabem; aqui só medimos o resultado
    function medir() {
      passo = total > 1 ? slides[1].offsetLeft - slides[0].offsetLeft : palco.clientWidth;
      if (passo <= 0) passo = palco.clientWidth;
      porVista = Math.max(1, Math.round(palco.clientWidth / passo));
      maxIdx = Math.max(0, total - porVista);
      if (atual > maxIdx) atual = maxIdx;
    }

    function criarPontos() {
      if (!pontos) return;
      pontos.innerHTML = '';
      for (let i = 0; i <= maxIdx; i++) {
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Posição ' + (i + 1) + ' de ' + (maxIdx + 1));
        b.addEventListener('click', function () { ir(i); reiniciar(); });
        pontos.appendChild(b);
      }
    }

    function ir(i) {
      if (i < 0) i = maxIdx;
      else if (i > maxIdx) i = 0;
      atual = i;
      trilha.style.transform = 'translateX(-' + (atual * passo) + 'px)';
      if (pontos) {
        Array.prototype.forEach.call(pontos.children, function (b, k) {
          if (k === atual) b.setAttribute('aria-current', 'true');
          else b.removeAttribute('aria-current');
        });
      }
      // esconde de leitores de tela e do teclado o que está fora da janela visível
      slides.forEach(function (s, k) {
        const fora = k < atual || k >= atual + porVista;
        s.setAttribute('aria-hidden', String(fora));
        s.inert = fora; // sem isso o "Ler mais" de um cartão escondido receberia foco
      });
    }

    function iniciar() {
      if (timer || semMovimento || maxIdx === 0) return;
      timer = setInterval(function () { ir(atual + 1); }, intervalo);
    }
    function parar() {
      if (timer) { clearInterval(timer); timer = null; }
    }
    function reiniciar() { parar(); iniciar(); }

    function recalcular() { medir(); criarPontos(); ir(atual); }

    const ant = carrossel.querySelector('.carrossel__seta--ant');
    const prox = carrossel.querySelector('.carrossel__seta--prox');
    if (ant) ant.addEventListener('click', function () { ir(atual - 1); reiniciar(); });
    if (prox) prox.addEventListener('click', function () { ir(atual + 1); reiniciar(); });

    // pausa enquanto o visitante está lendo ou com a aba em segundo plano
    carrossel.addEventListener('mouseenter', parar);
    carrossel.addEventListener('mouseleave', iniciar);
    carrossel.addEventListener('focusin', parar);
    carrossel.addEventListener('focusout', iniciar);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) parar(); else iniciar();
    });

    carrossel.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { ir(atual - 1); reiniciar(); }
      if (e.key === 'ArrowRight') { ir(atual + 1); reiniciar(); }
    });

    // arrastar com o dedo
    let toqueX = null;
    trilha.addEventListener('touchstart', function (e) { toqueX = e.touches[0].clientX; parar(); }, { passive: true });
    trilha.addEventListener('touchend', function (e) {
      if (toqueX === null) return;
      const dx = e.changedTouches[0].clientX - toqueX;
      if (Math.abs(dx) > 40) ir(atual + (dx < 0 ? 1 : -1));
      toqueX = null;
      iniciar();
    });

    // o número de cartões visíveis muda com a largura da tela
    let esperaResize = null;
    window.addEventListener('resize', function () {
      clearTimeout(esperaResize);
      esperaResize = setTimeout(recalcular, 150);
    });
    window.addEventListener('load', recalcular);

    recalcular();
    iniciar();
  });

  /* ---------- 4b. Depoimentos: botão "Ler mais" ---------- */
  const depoimentos = Array.prototype.slice.call(document.querySelectorAll('.depoimento'));
  if (depoimentos.length) {
    depoimentos.forEach(function (dep) {
      const texto = dep.querySelector('p');
      if (!texto) return;

      const botao = document.createElement('button');
      botao.type = 'button';
      botao.className = 'depoimento__mais';
      botao.textContent = 'Ler mais';
      botao.setAttribute('aria-expanded', 'false');
      texto.insertAdjacentElement('afterend', botao);

      botao.addEventListener('click', function () {
        const aberto = dep.classList.toggle('depoimento--aberto');
        botao.textContent = aberto ? 'Ler menos' : 'Ler mais';
        botao.setAttribute('aria-expanded', String(aberto));
      });
    });

    // o botão só aparece onde o texto realmente foi cortado pelas 5 linhas
    function conferirCortes() {
      depoimentos.forEach(function (dep) {
        if (dep.classList.contains('depoimento--aberto')) return; // aberto não dá para medir
        const texto = dep.querySelector('p');
        if (!texto) return;
        dep.classList.toggle('depoimento--truncado', texto.scrollHeight > texto.clientHeight + 1);
      });
    }

    conferirCortes();
    // as fontes serifadas mudam a altura das linhas quando terminam de carregar
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(conferirCortes);
    window.addEventListener('load', conferirCortes);

    let esperaCorte = null;
    window.addEventListener('resize', function () {
      clearTimeout(esperaCorte);
      esperaCorte = setTimeout(conferirCortes, 150);
    });
  }

  /* ---------- 5. Máscara de telefone ---------- */
  const campoTel = document.getElementById('telefone');
  if (campoTel) {
    campoTel.addEventListener('input', function () {
      let v = campoTel.value.replace(/\D/g, '').slice(0, 11);
      if (v.length > 6) {
        v = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
      } else if (v.length > 2) {
        v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
      } else if (v.length > 0) {
        v = '(' + v;
      }
      campoTel.value = v;
    });
  }

  /* ---------- 6. Formulário: validação + envio ---------- */
  const form = document.getElementById('formContato');
  const retorno = document.getElementById('formRetorno');

  // Arquivo no servidor que recebe o formulário e dispara o e-mail.
  // As credenciais ficam lá dentro, nunca aqui.
  const ENDPOINT = 'enviar.php';

  function valido(campo) {
    if (campo.type === 'checkbox') {
      return campo.checked;
    }
    if (campo.type === 'email') {
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(campo.value.trim());
    }
    if (campo.type === 'tel') {
      return campo.value.replace(/\D/g, '').length >= 10;
    }
    return campo.value.trim().length >= 3;
  }

  if (form) {
    // limpa o aviso de erro ao digitar
    form.querySelectorAll('input').forEach(function (campo) {
      campo.addEventListener('input', function () { campo.classList.remove('invalido'); });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const obrigatorios = form.querySelectorAll('[required]');
      let primeiroErro = null;

      obrigatorios.forEach(function (campo) {
        if (!valido(campo)) {
          campo.classList.add('invalido');
          if (!primeiroErro) primeiroErro = campo;
        } else {
          campo.classList.remove('invalido');
        }
      });

      if (primeiroErro) {
        if (retorno) {
          retorno.textContent = 'Confira os campos destacados antes de enviar.';
          retorno.className = 'formulario__retorno erro';
        }
        primeiroErro.focus();
        return;
      }

      // Envia para o servidor. O visitante permanece na página.
      const botao = form.querySelector('button[type="submit"]');
      const textoOriginal = botao ? botao.textContent : '';

      if (botao) {
        botao.disabled = true;
        botao.textContent = 'Enviando...';
      }
      if (retorno) {
        retorno.textContent = '';
        retorno.className = 'formulario__retorno';
      }

      fetch(ENDPOINT, { method: 'POST', body: new FormData(form) })
        .then(function (r) {
          // Lê como texto primeiro: se o servidor devolver algo que não seja
          // JSON, precisamos do conteúdo bruto para saber o que aconteceu.
          return r.text().then(function (texto) {
            var dados = null;
            try { dados = JSON.parse(texto); } catch (e) { /* não era JSON */ }
            return { status: r.status, dados: dados, bruto: texto };
          });
        })
        .then(function (res) {
          var msg, tipo;

          if (res.dados && res.dados.ok) {
            msg = 'Recebemos a sua solicitação. Retornamos em até 24 horas.';
            tipo = 'sucesso';
            form.reset();
          } else if (res.dados && res.dados.erro) {
            // O PHP rodou e explicou o motivo
            msg = res.dados.erro;
            tipo = 'erro';
          } else {
            // Resposta não é JSON: o PHP não rodou ou quebrou antes de responder
            tipo = 'erro';
            if (res.status === 404) {
              msg = 'O arquivo enviar.php não foi encontrado no servidor.';
            } else if (res.bruto.indexOf('<?php') !== -1) {
              msg = 'O servidor não está executando PHP nesta página.';
            } else {
              msg = 'O servidor respondeu de forma inesperada (HTTP ' + res.status + ').';
            }
            console.error('[formulario] HTTP ' + res.status + ' — resposta recebida:');
            console.error(res.bruto.slice(0, 800) || '(resposta vazia)');
          }

          if (retorno) {
            retorno.textContent = msg;
            retorno.className = 'formulario__retorno ' + tipo;
          }
        })
        .catch(function (e) {
          if (retorno) {
            retorno.textContent = 'Falha de conexão. Verifique a internet e tente de novo.';
            retorno.className = 'formulario__retorno erro';
          }
          console.error('[formulario] falha na requisição:', e);
        })
        .then(function () {
          if (botao) {
            botao.disabled = false;
            botao.textContent = textoOriginal;
          }
        });
    });
  }

  /* ---------- 7. Modal (Politica de Privacidade) ---------- */
  const modal = document.getElementById('modalPrivacidade');

  if (modal) {
    let disparador = null;   // quem abriu, para devolver o foco depois

    function abrirModal(origem) {
      disparador = origem || null;
      modal.classList.add('aberto');
      document.body.style.overflow = 'hidden';   // trava a rolagem do fundo
      const corpo = modal.querySelector('.modal__corpo');
      if (corpo) { corpo.scrollTop = 0; corpo.focus(); }
    }

    function fecharModal() {
      modal.classList.remove('aberto');
      document.body.style.overflow = '';
      if (disparador) { disparador.focus(); disparador = null; }
    }

    // Delegacao no documento: funciona mesmo que algum trecho da pagina
    // seja reescrito depois, o que descartaria ouvintes presos ao elemento.
    document.addEventListener('click', function (e) {
      const abre = e.target.closest ? e.target.closest('[data-abre="modalPrivacidade"]') : null;
      if (abre) { e.preventDefault(); abrirModal(abre); return; }

      if (!modal.classList.contains('aberto')) return;
      const fecha = e.target.closest ? e.target.closest('[data-fechar]') : null;
      if (fecha) { e.preventDefault(); fecharModal(); }
    });

    document.addEventListener('keydown', function (e) {
      if (!modal.classList.contains('aberto')) return;

      if (e.key === 'Escape') { fecharModal(); return; }

      // mantem o Tab circulando dentro do modal
      if (e.key !== 'Tab') return;
      const focaveis = modal.querySelectorAll('a[href], button, [tabindex="0"]');
      if (!focaveis.length) return;
      const primeiro = focaveis[0];
      const ultimo = focaveis[focaveis.length - 1];
      if (e.shiftKey && document.activeElement === primeiro) {
        e.preventDefault();
        ultimo.focus();
      } else if (!e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primeiro.focus();
      }
    });
  }

  /* ---------- 8. Ano automático no rodapé ---------- */
  const anoAtual = new Date().getFullYear();
  document.querySelectorAll('.rodape__base p').forEach(function (p) {
    // parágrafos com elementos dentro ficam de fora: reescrever innerHTML
    // recriaria os filhos e derrubaria os ouvintes de clique já registrados
    if (p.children.length) return;
    p.textContent = p.textContent.replace(/©[ ]*[0-9]{4}/, '© ' + anoAtual);
  });

})();
