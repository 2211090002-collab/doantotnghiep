<?php
/**
 * Template Name: RRP: Chatbot AI
 */
get_header(); ?>

<div id="rrp-chatbot" style="max-width:700px;margin:40px auto;padding:0 20px;">

  <h1 style="text-align:center;margin-bottom:8px;">🤖 Chatbot AI — RRP</h1>
  <p style="text-align:center;color:#6b7280;margin-bottom:24px;">
    Hỏi đáp về triệu chứng, bệnh hô hấp và kết quả đánh giá.
  </p>

  <!-- Khung chat -->
  <div class="rrp-chart-box" style="padding:0;overflow:hidden;">

    <!-- Tin nhắn -->
    <div id="chat-messages"
      style="height:420px;overflow-y:auto;padding:20px;
             display:flex;flex-direction:column;gap:12px;">

      <!-- Tin nhắn mặc định từ bot -->
      <div class="msg-bot">
        <div class="msg-bubble msg-bubble-bot">
          Xin chào! Tôi là trợ lý AI của hệ thống <strong>Respiratory Risk Prediction</strong>. 
          Bạn có thể hỏi tôi về triệu chứng bệnh hô hấp, kết quả đánh giá nguy cơ, 
          hoặc các thông tin sức khỏe liên quan. 😊
        </div>
      </div>

    </div>

    <!-- Gợi ý câu hỏi nhanh -->
    <div id="quick-questions"
      style="padding:12px 20px;border-top:1px solid #f3f4f6;
             display:flex;flex-wrap:wrap;gap:8px;">
      <button class="rrp-quick-btn"
        onclick="sendQuick('Hen suyễn có triệu chứng gì?')">
        Hen suyễn có triệu chứng gì?
      </button>
      <button class="rrp-quick-btn"
        onclick="sendQuick('Nguy cơ HIGH nghĩa là gì?')">
        Nguy cơ HIGH nghĩa là gì?
      </button>
      <button class="rrp-quick-btn"
        onclick="sendQuick('Khi nào cần đi bệnh viện?')">
        Khi nào cần đi bệnh viện?
      </button>
    </div>

    <!-- Input -->
    <div style="padding:16px 20px;border-top:1px solid #e5e7eb;
                display:flex;gap:10px;align-items:center;">
      <input type="text" id="chat-input"
        placeholder="Nhập câu hỏi của bạn..."
        style="flex:1;padding:10px 14px;border:1px solid #e5e7eb;
               border-radius:8px;font-size:0.95rem;outline:none;"
        onkeydown="if(event.key==='Enter') sendMessage()">
      <button onclick="sendMessage()" id="btn-send"
        style="padding:10px 20px;background:#3b82f6;color:#fff;
               border:none;border-radius:8px;cursor:pointer;
               font-size:0.95rem;font-weight:600;white-space:nowrap;">
        Gửi ➤
      </button>
    </div>

  </div>

  <!-- Cảnh báo -->
  <div style="margin-top:12px;padding:10px 14px;background:#f3f4f6;
              border-radius:8px;font-size:0.8rem;color:#6b7280;text-align:center;">
    ⚠️ Chatbot chỉ mang tính tham khảo, không thay thế tư vấn y tế chuyên nghiệp.
  </div>

</div>

<style>
.msg-bot, .msg-user {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.msg-user { flex-direction: row-reverse; }

.msg-bubble {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 0.92rem;
    line-height: 1.5;
}
.msg-bubble-bot {
    background: #f3f4f6;
    color: #1f2937;
    border-bottom-left-radius: 4px;
}
.msg-bubble-user {
    background: #3b82f6;
    color: #fff;
    border-bottom-right-radius: 4px;
}
.msg-bubble-loading {
    background: #f3f4f6;
    color: #9ca3af;
    font-style: italic;
}
.rrp-quick-btn {
    padding: 6px 12px;
    background: #eff6ff;
    color: #3b82f6;
    border: 1px solid #bfdbfe;
    border-radius: 20px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.rrp-quick-btn:hover {
    background: #3b82f6;
    color: #fff;
}
</style>

<script>
const API  = typeof RRP_CONFIG !== 'undefined' ? RRP_CONFIG.api_url : 'http://localhost:5000';
const role = typeof RRP_USER !== 'undefined' ? RRP_USER.role : 'PATIENT';

function appendMessage(text, sender) {
    const box = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = sender === 'user' ? 'msg-user' : 'msg-bot';
    div.innerHTML = `
        <div class="msg-bubble ${sender === 'user' ? 'msg-bubble-user' : 'msg-bubble-bot'}">
            ${text}
        </div>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return div;
}

function appendLoading() {
    const box = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className  = 'msg-bot';
    div.id         = 'msg-loading';
    div.innerHTML  = `<div class="msg-bubble msg-bubble-loading">⏳ Đang trả lời...</div>`;
    box.appendChild(div);
    box.scrollTop  = box.scrollHeight;
}

function removeLoading() {
    const el = document.getElementById('msg-loading');
    if (el) el.remove();
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;

    // Ẩn gợi ý sau lần đầu gửi
    document.getElementById('quick-questions').style.display = 'none';

    input.value = '';
    appendMessage(msg, 'user');
    appendLoading();

    document.getElementById('btn-send').disabled = true;

    try {
        const res  = await fetch(`${API}/api/chat`, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ message: msg, role: role })
        });
        const data = await res.json();
        removeLoading();
        appendMessage(data.reply, 'bot');
    } catch (err) {
        removeLoading();
        appendMessage('❌ Không thể kết nối máy chủ. Hãy chắc chắn Flask đang chạy.', 'bot');
    } finally {
        document.getElementById('btn-send').disabled = false;
        input.focus();
    }
}

function sendQuick(text) {
    document.getElementById('chat-input').value = text;
    sendMessage();
}
</script>

<?php get_footer(); ?>