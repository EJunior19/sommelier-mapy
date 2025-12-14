<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sommelier Mapy • Shopping Mapy</title>

  <style>
  :root {
    --azul-escuro: #001a4d;
    --azul-medio: #003b8a;
    --dourado: #ffd84d;
    --branco: #ffffff;
    --bolha-cliente: #1a3e7a;
    --bolha-assistente: #ffd84d;
  }

  body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(180deg, var(--azul-escuro), var(--azul-medio));
    font-family: "Segoe UI", sans-serif;
    color: var(--branco);
    overflow: hidden;
  }

  .container {
    width: 400px;
    height: 80vh;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 25px;
    box-shadow: 0 0 25px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
  }

  .header {
    text-align: center;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }

  .header h1 {
    margin: 0;
    font-size: 1.4rem;
    background: linear-gradient(90deg, var(--dourado), #fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .chat-area {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
    z-index: 2; /* mensagens ficam na frente do robô */
  }

  .msg {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 15px;
    line-height: 1.4;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    word-wrap: break-word;
    white-space: pre-line;
    display: block;
  }

  .user {
    align-self: flex-end;
    background: var(--bolha-cliente);
    color: var(--branco);
    border-bottom-right-radius: 4px;
  }

  .assistant {
    align-self: flex-start;
    background: var(--bolha-assistente);
    color: #00285f;
    border-bottom-left-radius: 4px;
  }

  .typing {
    font-style: italic;
    opacity: 0.8;
    font-size: 14px;
    color: #ccc;
  }

  .input-area {
    display: flex;
    align-items: center;
    padding: 10px;
    background: #012d70;
  }

  .input-area input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    color: white;
    font-size: 15px;
    padding: 8px;
  }

  .input-area input::placeholder { color: #b8c8f0; }

  .btn {
    background: linear-gradient(180deg, var(--dourado), #fbbd00);
    color: #00285f;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    margin-left: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    transition: 0.2s;
  }

  .btn:hover { transform: scale(1.1); }

  #btnReset {
    background: transparent;
    border: none;
    color: var(--dourado);
    font-size: 18px;
    margin-left: 8px;
    cursor: pointer;
  }

  /* Robô fixo e centralizado */
  #bot {
    position: absolute;
    top: 50%;
    left: 50%;
    width: min(380px, 70%);
    height: auto;
    transform: translate(-50%, -50%);
    opacity: 0.35;
    pointer-events: none;
    filter: drop-shadow(0 0 25px rgba(255, 255, 255, 0.5));
    z-index: 0;
    transition: filter 0.4s ease;
  }

  /* 🔊 Efeito luminoso ao falar — sem mover o robô */
  #bot.falando {
    filter: brightness(1.4) drop-shadow(0 0 25px #ffea88);
  }

  /* 🌟 Efeito pulsante visual independente da imagem */
  #bot.falando::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    animation: pulseGlow 1.5s infinite ease-in-out;
    background: radial-gradient(circle, rgba(255,232,150,0.35) 0%, transparent 70%);
    z-index: -1;
  }

  @keyframes pulseGlow {
    0%, 100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
    50% { opacity: 0.7; transform: translate(-50%, -50%) scale(1.08); }
  }

  /* estado gravando no botão */
  .gravando {
    background: linear-gradient(180deg, #ff4d4d, #cc0000) !important;
    box-shadow: 0 0 12px rgba(255, 100, 100, 0.8);
  }
  </style>
</head>

<body>
  <div class="container">
    <div class="header">
      <h1>🍷 Vendedor Bebidas  <span style="color:#ffd84d;">Mapy</span></h1>
      <small>Atendente Virtual • Salto del Guairá</small>
    </div>

    <div id="chat" class="chat-area">
      <div class="msg assistant">Carregando Sommelier Virtual...</div>
    </div>

    <img src="{{ asset('img/robot.png') }}" id="bot" alt="Sommelier Bot">

    <div class="input-area">
      <input id="mensagem" placeholder="Digite sua mensagem..." />
      <button class="btn" id="mic" title="Pressione e segure para falar">🎤</button>
      <button class="btn" id="enviar" title="Enviar">➤</button>
      <button id="btnReset" title="Reiniciar">🔄</button>
    </div>
  </div>

<script>
const chat   = document.getElementById('chat');
const input  = document.getElementById('mensagem');
const csrf   = document.querySelector('meta[name="csrf-token"]').content;
const bot    = document.getElementById('bot');
const micBtn = document.getElementById('mic');

let vozAtiva    = false;
let enviando    = false;
let vozFeminina = null;

// =======================
//     INICIALIZAÇÃO DE VOZ (FALLBACK LOCAL)
// =======================
function initVoice() {
  if (!('speechSynthesis' in window)) return;
  if (vozAtiva) return;

  const dummy = new SpeechSynthesisUtterance('Olá!');
  dummy.lang = 'pt-BR';
  speechSynthesis.speak(dummy);
  vozAtiva = true;

  const vozes = speechSynthesis.getVoices();
  vozFeminina =
    vozes.find(v => v.name.includes('Google português do Brasil')) ||
    vozes.find(v => v.name.includes('Maria')) ||
    vozes.find(v => v.lang === 'pt-BR');
}

document.addEventListener('click', initVoice);
document.addEventListener('keydown', initVoice);
if (speechSynthesis.onvoiceschanged !== undefined) {
  speechSynthesis.onvoiceschanged = initVoice;
}

// =======================
//       CHAT UI
// =======================
function addMessage(text, from) {
  const div = document.createElement('div');
  div.className = 'msg ' + from;
  div.textContent = text;
  chat.appendChild(div);
  chat.scrollTop = chat.scrollHeight;
}

function updateLastUserMessage(text) {
  const msgs = document.querySelectorAll('.msg.user');
  if (msgs.length === 0) return;
  msgs[msgs.length - 1].textContent = text;
}

function addTyping() {
  removeTyping();
  const div = document.createElement('div');
  div.className = 'typing';
  div.textContent = 'Sommelier está digitando...';
  div.id = 'typing';
  chat.appendChild(div);
  chat.scrollTop = chat.scrollHeight;
  bot.classList.add('pensando');
}

function removeTyping() {
  const t = document.getElementById('typing');
  if (t) t.remove();
  bot.classList.remove('pensando');
}

// =======================
//       FALA NATURAL
// =======================
function playVoice(resposta, audioUrl = null) {
  bot.classList.remove('pensando');

  // usa o MP3 vindo do backend
  if (audioUrl) {
    const audio = new Audio(audioUrl);
    bot.classList.add('falando');
    audio.play();
    audio.onended = () => bot.classList.remove('falando');
    audio.onerror = () => speakLocal(resposta);
    return;
  }

  // fallback: TTS do navegador
  speakLocal(resposta);
}

function speakLocal(text) {
  if (!vozAtiva || !('speechSynthesis' in window)) return;

  const partes = text
    .split(/([.!?,])/)
    .reduce((acc, cur) => {
      if (/[.!?,]/.test(cur) && acc.length) acc[acc.length - 1] += cur;
      else if (cur.trim()) acc.push(cur.trim());
      return acc;
    }, []);

  let i = 0;
  speechSynthesis.cancel();

  const falarParte = () => {
    if (i >= partes.length) {
      bot.classList.remove('falando');
      return;
    }

    const frase = partes[i];
    const u = new SpeechSynthesisUtterance(frase);
    u.lang   = 'pt-BR';
    u.pitch  = 1.05;
    u.rate   = 1.03;
    u.volume = 1;
    if (vozFeminina) u.voice = vozFeminina;

    u.onend = () => {
      i++;
      const pausa = /[.!?]/.test(frase) ? 600 : /,/.test(frase) ? 300 : 100;
      setTimeout(falarParte, pausa);
    };

    if (i === 0) bot.classList.add('falando');
    speechSynthesis.speak(u);
  };

  falarParte();
}

// =======================
//     ENVIO TEXTO
// =======================
async function sendMessage(message) {
  if (enviando || !message.trim()) return;
  enviando = true;

  addMessage(message, 'user');
  addTyping();
  input.value = '';
  micBtn.disabled = true;
  micBtn.style.opacity = '0.6';

  try {
    const res = await fetch('{{ route("asistente.responder") }}', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({ mensagem: message })
    });

    const data = await res.json();
    removeTyping();

    const resposta = data.resposta || 'Erro ao responder.';
    addMessage(resposta, 'assistant');
    playVoice(resposta, data.audio_url);
  } catch (error) {
    console.error('⚠️ Erro ao enviar mensagem:', error);
    removeTyping();
    addMessage('Desculpe, ocorreu um erro ao enviar sua mensagem. 😢', 'assistant');
  } finally {
    micBtn.disabled = false;
    micBtn.style.opacity = '1';
    enviando = false;
  }
}

// =======================
//   EVENTOS DE TEXTO
// =======================
document.getElementById('enviar').addEventListener('click', () => {
  const msg = input.value.trim();
  if (msg) sendMessage(msg);
});

input.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const msg = input.value.trim();
    if (msg) sendMessage(msg);
  }
});

document.getElementById('btnReset').addEventListener('click', () => {
  chat.innerHTML = '<div class="msg assistant">Chat reiniciado 🍇. O que você gostaria de beber hoje?</div>';
  playVoice('Chat reiniciado. O que você gostaria de beber hoje?');
});

// =======================
//   SAUDAÇÃO INICIAL
// =======================
window.addEventListener('load', () => {
  chat.innerHTML = "";

  const h = new Date().getHours();
  let saudacao = h < 12 ? 'Ótimo dia ☀️' : h < 18 ? 'Ótima tarde 🌤️' : 'Ótima noite 🌙';

  const texto =
    `${saudacao}! Bem-vindo ao Shopping Mapy. ` +
    `Sou sua Sommelier Virtual 🍷 — posso sugerir algo leve, doce ou encorpado?`;

  addMessage(texto, 'assistant');
  playVoice(texto);
});

// =======================
//   GRAVAÇÃO DE ÁUDIO (MODO WHATSAPP)
// =======================
let mediaRecorder = null;
let audioChunks   = [];
let gravando      = false;
let currentStream = null;

async function startRecording() {
  if (gravando || enviando) return;

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    alert('Seu navegador não suporta gravação de áudio.');
    return;
  }

  try {
    currentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(currentStream);
    audioChunks = [];

    mediaRecorder.ondataavailable = (event) => {
      if (event.data && event.data.size > 0) {
        audioChunks.push(event.data);
      }
    };

    mediaRecorder.onstop = () => {
      gravando = false;
      micBtn.classList.remove('gravando');
      micBtn.textContent = '🎤';

      if (currentStream) {
        currentStream.getTracks().forEach(t => t.stop());
        currentStream = null;
      }

      if (audioChunks.length === 0) return;

      const blob = new Blob(audioChunks, { type: 'audio/webm' });
      audioChunks = [];
      sendAudioMessage(blob);
    };

    mediaRecorder.start();
    gravando = true;
    micBtn.classList.add('gravando');
    micBtn.textContent = '●';

  } catch (err) {
    console.error('Erro ao acessar microfone:', err);
    alert('Não foi possível acessar o microfone.');
  }
}

function stopRecording() {
  if (!gravando || !mediaRecorder) return;
  mediaRecorder.stop();
}

// =================================================
//   ENVIO DE ÁUDIO — CORRIGIDO (MENSAGEM IMEDIATA)
// =================================================
async function sendAudioMessage(blob) {
  if (enviando) return;
  enviando = true;

  // 1️⃣ Mostra imediatamente
  addMessage("⏳ Transcrevendo seu áudio...", "user");

  addTyping();
  micBtn.disabled = true;
  micBtn.style.opacity = '0.6';

  const formData = new FormData();
  formData.append('audio', blob, 'voz.webm');

  try {
    const res = await fetch('{{ route("asistente.responder") }}', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf
      },
      body: formData
    });

    const data = await res.json();
    removeTyping();

    // 2️⃣ Substitui o placeholder pelo texto transcrito
    if (data.texto && data.texto.trim() !== "") {
      updateLastUserMessage(data.texto);
    } else {
      updateLastUserMessage("Não consegui entender o áudio.");
    }

    // 3️⃣ Resposta do sommelier
    const resposta = data.resposta || 'Erro ao responder.';
    addMessage(resposta, 'assistant');
    playVoice(resposta, data.audio_url);

  } catch (error) {
    console.error('⚠️ Erro ao enviar áudio:', error);
    removeTyping();
    addMessage('Desculpe, ocorreu um erro ao processar seu áudio. 😢', 'assistant');
  } finally {
    micBtn.disabled = false;
    micBtn.style.opacity = '1';
    enviando = false;
  }
}

// =======================
//   EVENTOS DE ÁUDIO
// =======================
micBtn.addEventListener('click' , () => {
  if (!gravando){
    // Inicia gravação
    startRecording();
    micBtn.classList.add('gravando');
    micBtn.textContent = '●';
  } else{
    //Para gravação
    stopRecording();
    micBtn.classList.remove('gravando');
    micBtn.textContent = '🎤';
  }
})

</script>
</body>
</html>
