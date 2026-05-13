<?php
session_start();

// Check if the user is already fully authenticated
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    $role = $_SESSION['user_role'] ?? 'student';
    $targetPage = 'dashboard.php';

    switch ($role) {
        case 'admin':
            $targetPage = 'admindashboard.php';
            break;
        case 'faculty':
            $targetPage = 'facultydashboard.php';
            break;
        case 'student':
        default:
            $targetPage = 'dashboard.php';
            break;
    }

    header("Location: " . $targetPage);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UDMap – The Campus Quest</title>
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;700;900&display=swap');

    :root {
      --star-red: #ff3131;
      --star-red-dark: #b31d1d;
      --star-gold: #ffda6c;
      --moon-light: rgba(107, 255, 216, 0.6);
      --firefly-glow: #b6ff92;
    }

    body, html {
      margin: 0; padding: 0;
      width: 100vw; height: 100vh;
      overflow: hidden;
      background: #011a10;
    }

    /* Transition Overlay */
    #transition-overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: #051a05; 
      opacity: 0; 
      z-index: 1000; 
      pointer-events: none;
    }

    body::after {
      content: "";
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: radial-gradient(circle, transparent 35%, rgba(0,0,0,0.8) 100%);
      pointer-events: none;
      z-index: 5;
    }

    #forestCanvas, #particleCanvas {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      display: block;
    }

    #forestCanvas { z-index: 1; }
    #particleCanvas { z-index: 2; pointer-events: none; }

    .ui-wrapper {
      position: absolute;
      top: 0; left: 0;
      z-index: 10;
      display: flex;
      flex-direction: column;
      align-items: center;
      pointer-events: none;
      width: 100%; height: 100%;
      justify-content: space-evenly;
    }

    .ui-wrapper > * { pointer-events: auto; }

    /* HEADER */
    .quest-header { text-align: center; }
    .title-banner {
      font-family: 'Cinzel', serif;
      font-size: 2rem;
      color: #fff;
      text-shadow: 0 0 20px rgba(0, 0, 0, 1);
      letter-spacing: 4px;
      margin-bottom: 5px;
    }

    .xp-container { width: 250px; margin: 10px auto; }
    .xp-label {
        font-size: 13px; text-transform: uppercase; letter-spacing: 1px;
        margin-bottom: 8px; color: var(--star-gold); font-weight: 900;
        text-align: center; white-space: nowrap; display: block; width: 100%;
    }

    .xp-bar-bg {
      height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px;
      border: 1px solid rgba(255,255,255,0.15); overflow: hidden;
    }

    .xp-bar-fill {
      width: 15%; height: 100%; background: linear-gradient(90deg, #003021, #52ff00);
      box-shadow: 0 0 10px #00703c;
    }

    /* COMPASS */
    .compass-outer {
      position: relative; width: 420px; height: 420px; border-radius: 50%;
      background: #000; border: 3px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 0 80px rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center;
    }

    .top-indicator {
      position: absolute; top: -24px; width: 42px; height: 52px;
      z-index: 110; left: 50%; transform: translateX(-50%);
    }

    .top-indicator::before {
      content: ''; position: absolute; border-left: 21px solid transparent;
      border-right: 21px solid transparent; border-top: 52px solid var(--star-red);
      clip-path: polygon(50% 0, 50% 100%, 0 0);
    }

    .top-indicator::after {
      content: ''; position: absolute; border-left: 21px solid transparent;
      border-right: 21px solid transparent; border-top: 52px solid var(--star-red-dark);
      clip-path: polygon(50% 0, 100% 0, 50% 100%);
    }

    .moon-glow {
      position: absolute; width: 125%; height: 125%;
      background: radial-gradient(circle at center, rgba(255, 255, 255, 0.5) 0%, var(--moon-light) 25%, transparent 65%);
      z-index: 5; pointer-events: none; filter: blur(25px);
    }

    .ghost-ticks {
      position: absolute; width: 100%; height: 100%;
      background: repeating-conic-gradient(from -0.5deg, rgba(255,255,255,0.08) 0 1deg, transparent 0 30deg);
      border-radius: 50%; z-index: 2;
    }

    .rose-wrapper { position: relative; width: 330px; height: 330px; z-index: 10; }

    .compass-rose {
      width: 100%; height: 100%;
      background: conic-gradient(
        from 0deg,
        #d72d2d 0deg 22.5deg, #ffda6c 22.5deg 45deg,
        #035208 45deg 67.5deg, #0c2802 67.5deg 90deg,
        #ffffff 90deg 112.5deg, #bbb 112.5deg 135deg,
        #035208 135deg 157.5deg, #0c2802 157.5deg 180deg,
        #fff 180deg 202.5deg, #bbb 202.5deg 225deg,
        #035208 225deg 247.5deg, #0c2802 247.5deg 270deg,
        #fff 270deg 292.5deg, #bbb 292.5deg 315deg,
        #035208 315deg 337.5deg, #0c2802 360deg
      );
      clip-path: polygon(
        50% 0%, 54% 37%, 85% 15%, 63% 47%,
        100% 50%, 63% 53%, 85% 85%, 54% 63%,
        50% 100%, 46% 63%, 15% 85%, 37% 53%,
        0% 50%, 37% 47%, 15% 15%, 46% 37%
      );
    }

    .label {
      position: absolute; font-weight: 900; font-size: 26px; color: white;
      text-shadow: 0 0 10px rgba(0,0,0,1); z-index: 20; font-family: 'Cinzel', serif;
    }
    .lbl-n { top: -45px; left: 50%; transform: translateX(-50%); color: var(--star-red); }
    .lbl-s { bottom: -45px; left: 50%; transform: translateX(-50%); }
    .lbl-e { right: -45px; top: 50%; transform: translateY(-50%); }
    .lbl-w { left: -45px; top: 50%; transform: translateY(-50%); }

    /* HELP PORTAL & AI CHAT WIDGET */
    .event-portal {
      position: absolute; top: 30px; left: 30px; z-index: 100; 
      display: flex; flex-direction: column; align-items: center; cursor: pointer; pointer-events: auto;
    }

    .portal-circle {
      width: 55px; height: 55px; border-radius: 50%;
      background: radial-gradient(circle, var(--moon-light) 0%, transparent 80%);
      border: 2px dashed rgba(255,255,255,0.4); box-shadow: 0 0 15px var(--moon-light);
      display: flex; align-items: center; justify-content: center;
      animation: rotate-portal 15s linear infinite;
    }

    .portal-circle::before {
      content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; 
      background: var(--moon-light); opacity: 0.15; animation: pulse-magic 2.5s ease-out infinite;
    }

    .portal-circle::after {
      content: '?'; color: white; font-family: 'Cinzel', serif; font-size: 20px; 
      text-shadow: 0 0 8px #b6ff92; animation: counter-rotate 15s linear infinite;
    }

    .event-portal span {
      margin-top: 8px; font-family: 'Nunito', sans-serif; font-size: 9px; font-weight: 900; 
      color: #fff; text-transform: uppercase; letter-spacing: 2px; opacity: 0.6;
    }

    @keyframes rotate-portal { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes counter-rotate { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }
    @keyframes pulse-magic { 0% { transform: scale(1); opacity: 0.3; } 100% { transform: scale(1.6); opacity: 0; } }

    /* --- NEW: AI CHAT MODAL STYLES --- */
    .chat-modal {
      position: absolute; top: 110px; left: 30px; width: 320px; height: 420px;
      background: rgba(1, 26, 16, 0.85); border: 1px solid var(--star-gold);
      border-radius: 12px; display: flex; flex-direction: column;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.8), 0 0 15px rgba(255, 218, 108, 0.2);
      backdrop-filter: blur(8px); z-index: 200; pointer-events: auto;
      transform: scale(0); opacity: 0; transform-origin: top left;
      transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .chat-modal.active { transform: scale(1); opacity: 1; }

    .chat-header {
      background: rgba(0, 0, 0, 0.6); padding: 12px 15px; border-bottom: 1px dashed var(--star-gold);
      display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;
    }
    .chat-title { font-family: 'Cinzel', serif; color: var(--star-gold); font-size: 1rem; font-weight: bold; }
    .close-chat { background: none; border: none; color: #fff; cursor: pointer; font-size: 1.2rem; opacity: 0.7; }
    .close-chat:hover { opacity: 1; color: var(--star-red); }

    .chat-body {
      flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;
      font-family: 'Nunito', sans-serif; font-size: 0.85rem;
    }
    
    .msg { max-width: 85%; padding: 10px 14px; border-radius: 8px; line-height: 1.4; }
    .msg.bot { background: rgba(182, 255, 146, 0.1); color: #e6f2ec; border: 1px solid rgba(182, 255, 146, 0.3); align-self: flex-start; border-top-left-radius: 2px; }
    .msg.user { background: linear-gradient(90deg, #014d31, #013220); color: #fff; border: 1px solid var(--star-gold); align-self: flex-end; border-top-right-radius: 2px; }
    .msg.loading { font-style: italic; opacity: 0.6; }

    .chat-footer { padding: 10px; border-top: 1px dashed rgba(255, 255, 255, 0.2); display: flex; gap: 8px; }
    .chat-input {
      flex: 1; background: rgba(0, 0, 0, 0.5); border: 1px solid var(--moon-light); color: #fff;
      border-radius: 20px; padding: 8px 15px; font-family: 'Nunito', sans-serif; outline: none;
    }
    .chat-input:focus { border-color: var(--firefly-glow); box-shadow: 0 0 5px var(--firefly-glow); }
    .chat-send {
      background: var(--star-gold); color: #000; border: none; border-radius: 50%;
      width: 35px; height: 35px; font-weight: bold; cursor: pointer; transition: 0.2s;
    }
    .chat-send:hover { background: #fff; transform: scale(1.05); }

    /* Custom Scrollbar for Chat */
    .chat-body::-webkit-scrollbar { width: 5px; }
    .chat-body::-webkit-scrollbar-thumb { background: var(--star-gold); border-radius: 5px; }

    /* NAVIGATION BUTTONS */
    .btn-group { display: flex; flex-direction: column; gap: 10px; width: 300px; }
    .btn-action {
      padding: 12px; border: none; border-radius: 40px;
      font-weight: 900; cursor: pointer; transition: 0.3s;
      font-family: 'Nunito', sans-serif;
    }
    .btn-guest { background: transparent; color: #fff; border: 2px solid #fff; }
    .btn-student { background: linear-gradient(90deg, #014d31, #013220); color: white; border: 1px solid var(--firefly-glow); }
    .btn-faculty { background: #fff; color: #000; }
    
    .btn-signup { 
      background: transparent; color: var(--star-gold); border: 1px dashed var(--star-gold); margin-top: 5px;
    }
    .btn-signup:hover { background: rgba(255, 218, 108, 0.1); box-shadow: 0 0 10px rgba(255, 218, 108, 0.2); }

    .footer-note {
      position: absolute; bottom: 15px; font-size: 10px;
      color: rgba(255, 255, 255, 0.4); display: flex; align-items: center; gap: 10px;
    }
    .admin-link { color: #fff; text-decoration: underline; cursor: pointer; opacity: 0.6;}
  </style>
</head>
<body>

  <div id="transition-overlay"></div>
  <canvas id="forestCanvas"></canvas>
  <canvas id="particleCanvas"></canvas>

  <div class="ui-wrapper">
    <div class="event-portal" onclick="toggleChat()">
      <div class="portal-circle"></div>
      <span>Support</span>
    </div>

    <div class="chat-modal" id="aiChatModal">
      <div class="chat-header">
        <span class="chat-title">✨ Mystic Guide</span>
        <button class="close-chat" onclick="toggleChat()">✖</button>
      </div>
      <div class="chat-body" id="chatBody">
        <div class="msg bot">Greetings, traveler. I am the Mystic Guide. Ask me anything about the campus, schedules, or the realm itself.</div>
      </div>
      <div class="chat-footer">
        <input type="text" id="chatInput" class="chat-input" placeholder="Ask your question..." onkeypress="handleKeyPress(event)">
        <button class="chat-send" onclick="sendMessage()">➤</button>
      </div>
    </div>

    <div class="quest-header">
      <h1 class="title-banner">UDMap: Campus Journey</h1>
      <div class="xp-container">
        <div class="xp-label" id="xpLabel">Explorer Level 1 — Select Role</div>
        <div class="xp-bar-bg"><div class="xp-bar-fill"></div></div>
      </div>
    </div>

    <div class="compass-outer">
      <div class="top-indicator"></div>
      <div class="moon-glow"></div>
      <div class="ghost-ticks"></div>
      <div class="rose-wrapper" id="rose">
        <div class="compass-rose"></div>
        <div class="label lbl-n">N</div>
        <div class="label lbl-e">E</div>
        <div class="label lbl-s">S</div>
        <div class="label lbl-w">W</div>
      </div>
    </div>

    <div class="readout">
      <div class="btn-group">
        <button class="btn-action btn-guest" onclick="route('Guest')">Explore as Guest</button>
        <div style="display:flex; gap:10px;">
           <button class="btn-action btn-student" onclick="route('Student')" style="flex:1">Student</button>
           <button class="btn-action btn-faculty" onclick="route('Faculty')" style="flex:1">Faculty</button>
        </div>
        <button class="btn-action btn-signup" onclick="route('Signup')">New Explorer? Sign Up</button>
      </div>
    </div>

    <div class="footer-note">
      <span>Powered by Smart Campus Initiative</span>
      <span class="admin-link" onclick="route('Admin')">Admin Portal</span>
    </div>
  </div>

<script>
    // --- NEW: AI CHAT LOGIC ---
    function toggleChat() {
        const modal = document.getElementById('aiChatModal');
        modal.classList.toggle('active');
        if (modal.classList.contains('active')) {
            document.getElementById('chatInput').focus();
        }
    }

    function handleKeyPress(e) {
        if (e.key === 'Enter') sendMessage();
    }

    async function sendMessage() {
        const inputField = document.getElementById('chatInput');
        const chatBody = document.getElementById('chatBody');
        const userText = inputField.value.trim();
        
        if (!userText) return;

        // 1. Append User Message
        appendMessage(userText, 'user');
        inputField.value = '';

        // 2. Append Loading Indicator
        const loadingId = 'loading-' + Date.now();
        appendMessage('Consulting the scrolls...', 'bot loading', loadingId);

        // 3. Call AI Backend (Gemini/ChatGPT integration)
        try {
            /* IMPORTANT: Do NOT expose API keys in frontend JS.
               Create a PHP file (e.g., ai_handler.php) that securely holds your Gemini/OpenAI key
               and performs the cURL request. Then, fetch that file here.
               
               Example Fetch structure:
               const response = await fetch('ai_handler.php', {
                   method: 'POST',
                   headers: { 'Content-Type': 'application/json' },
                   body: JSON.stringify({ message: userText })
               });
               const data = await response.json();
               const botReply = data.reply;
            */

            // SIMULATED RESPONSE (Replace this setTimeout block with the fetch above)
            setTimeout(() => {
                document.getElementById(loadingId).remove();
                appendMessage(`As a mystic guide, I sense you are asking about "${userText}". Since my magical API connection is not yet configured on the server, I can only echo your thoughts.`, 'bot');
            }, 1500);

        } catch (error) {
            document.getElementById(loadingId).remove();
            appendMessage("The connection to the arcane network failed. Please try again.", 'bot');
        }
    }

    function appendMessage(text, senderClass, id = '') {
        const chatBody = document.getElementById('chatBody');
        const msgDiv = document.createElement('div');
        msgDiv.className = `msg ${senderClass}`;
        if (id) msgDiv.id = id;
        msgDiv.textContent = text;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight; // Auto-scroll to bottom
    }


    // --- EXISTING SENSOR & GRAPHICS LOGIC ---
    const rose = document.getElementById('rose');
    const headDisplay = document.getElementById('heading'); 

    function updateHeading(e) {
      let val = e.webkitCompassHeading || (360 - e.alpha);
      if (val) {
        if (headDisplay) headDisplay.innerText = `${Math.round(val)}°`;
        rose.style.transform = `rotate(${-val}deg)`;
      }
    }

    const startSensors = () => {
      if (typeof DeviceOrientationEvent.requestPermission === 'function') {
        DeviceOrientationEvent.requestPermission()
          .then(res => {
            if (res === 'granted') {
              window.addEventListener('deviceorientation', updateHeading);
            }
          }).catch(console.error);
      } else {
        window.addEventListener('deviceorientation', updateHeading);
      }
    };

    window.addEventListener('touchstart', startSensors, { once: true });
    window.addEventListener('mousedown', startSensors, { once: true });

    // Three.js Scene Setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x011a10);
    scene.fog = new THREE.FogExp2(0x011a10, 0.002);
    
    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 2000);
    camera.position.set(0, 40, 450); 
    
    const renderer = new THREE.WebGLRenderer({ canvas: document.querySelector("#forestCanvas"), antialias: true });
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);

    scene.add(new THREE.AmbientLight(0x404040, 2.5));
    const ground = new THREE.Mesh(new THREE.PlaneGeometry(3000, 3000), new THREE.MeshLambertMaterial({ color: 0x051a05 }));
    ground.rotation.x = -Math.PI / 2;
    scene.add(ground);

    const forestGroup = new THREE.Group();
    scene.add(forestGroup);

    function createTree() {
      const tree = new THREE.Group();
      tree.position.set((Math.random() - 0.5) * 2000, 0, (Math.random() - 0.5) * 2000);
      const leafMat = new THREE.MeshLambertMaterial({ color: 0x013220 });
      const trunk = new THREE.Mesh(new THREE.CylinderGeometry(2, 3, 15), new THREE.MeshLambertMaterial({ color: 0x1a0d00 }));
      trunk.position.y = 7.5; tree.add(trunk);
      for (let i = 0; i < 3; i++) {
        const foliage = new THREE.Mesh(new THREE.ConeGeometry(15 - i*3, 20), leafMat);
        foliage.position.y = 15 + (i * 8); tree.add(foliage);
      }
      tree.scale.set(0,0,0);
      forestGroup.add(tree);
      gsap.to(tree.scale, { x: 1, y: 1, z: 1, duration: 2, delay: Math.random() * 2 });
    }
    for (let i = 0; i < 450; i++) createTree();

    // Particle System
    const pCanvas = document.getElementById('particleCanvas');
    const pCtx = pCanvas.getContext('2d');
    let particles = [];
    function initParticles() {
      pCanvas.width = window.innerWidth; pCanvas.height = window.innerHeight;
      particles = [];
      for (let i = 0; i < 100; i++) {
        particles.push({ x: Math.random() * pCanvas.width, y: Math.random() * pCanvas.height, s: Math.random() * 1.5 + 0.5, vx: (Math.random() - 0.5) * 0.4, vy: (Math.random() - 0.5) * 0.4, o: Math.random() });
      }
    }
    function drawParticles() {
      pCtx.clearRect(0, 0, pCanvas.width, pCanvas.height);
      particles.forEach(p => {
        p.x += p.vx; p.y += p.vy;
        pCtx.fillStyle = `rgba(182, 255, 146, ${p.o})`;
        pCtx.beginPath(); pCtx.arc(p.x, p.y, p.s, 0, Math.PI*2); pCtx.fill();
      });
      requestAnimationFrame(drawParticles);
    }

    function animate() { requestAnimationFrame(animate); renderer.render(scene, camera); }
    initParticles(); drawParticles(); animate();

    /* TRANSITION LOGIC */
    function route(role) {
      const xpLabel = document.getElementById('xpLabel');
      const xpFill = document.querySelector('.xp-bar-fill');
      const uiWrapper = document.querySelector('.ui-wrapper');
      
      if (xpLabel) {
         xpLabel.innerText = role === 'Signup' ? "Creating a New Path..." : "Quest Start: Traveling...";
      }
      
      gsap.to(xpFill, { width: "100%", duration: 0.8, ease: "power2.inOut" });

      gsap.to(uiWrapper, { 
          opacity: 0, 
          scale: 1.3, 
          duration: 1.2, 
          ease: "power2.in",
          pointerEvents: "none"
      });

      gsap.to(camera.position, {
          z: -600, 
          duration: 2.2, 
          ease: "expo.in",
          onUpdate: () => { 
            scene.fog.density += 0.003; 
          }
      });

      const overlay = document.getElementById('transition-overlay');
      if (overlay) {
          gsap.to(overlay, { opacity: 1, duration: 1, delay: 1.4 });
      }
    
      setTimeout(() => {
          const routes = {
              'Student': "login.php?role=student",
              'Faculty': "login.php?role=faculty",
              'Admin': "login.php?role=admin",
              'Signup': "signup.html",
              'Guest': "login.php?role=guest"
          };
          if (routes[role]) window.location.href = routes[role];
      }, 2400);
    }
</script>
</body>
</html>