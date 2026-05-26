const API =
  typeof RRP_CONFIG !== "undefined"
    ? RRP_CONFIG.api_url
    : "http://localhost:5000";

document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("hs-total")) loadHomeStats();
  if (document.getElementById("articles-grid")) loadArticles(1);
  if (document.getElementById("stat-total")) loadDashboard();
  if (document.getElementById("test-body")) loadTests();
  if (document.getElementById("table-models")) loadModels();
  if (document.getElementById("cfg-threshold-low")) loadConfig();
  if (document.getElementById("codes-body")) loadCodes();
  if (document.getElementById("users-body")) loadUsers();
  if (document.getElementById("logs-body")) loadLogs();
  if (document.getElementById("chart-correlation")) loadCorrelationHeatmap();
  if (document.getElementById("versions-body")) loadVersions();
});

// ── TABS ─────────────────────────────────────────────────
function switchTab(tabId, btn) {
  document
    .querySelectorAll(".rrp-tab-content")
    .forEach((t) => (t.style.display = "none"));
  document
    .querySelectorAll(".rrp-tab")
    .forEach((b) => b.classList.remove("active"));
  document.getElementById(tabId).style.display = "block";
  btn.classList.add("active");
}

// ── INSIGHTS ─────────────────────────────────────────────
function generateInsights(data) {
  const s = data.summary || {};
  const insights = [];

  if (!s.total_records || !data.top_diseases?.length) {
    return [
      {
        icon: "ℹ️",
        color: "#3b82f6",
        text: "Chưa có đủ dữ liệu để tạo nhận định tự động.",
      },
    ];
  }

  const total = Number(s.total_records);
  const positive = Number(s.positive_cases || 0);
  const avgAge = Number(s.avg_age || 0);

  const topDisease = data.top_diseases[0];
  const diseaseRate = ((topDisease.count / total) * 100).toFixed(1);
  const positiveRate = ((positive / total) * 100).toFixed(1);

  let riskColor = "#10b981",
    riskIcon = "✅",
    riskText = "thấp";
  if (positiveRate >= 70) {
    riskColor = "#ef4444";
    riskIcon = "🚨";
    riskText = "rất cao";
  } else if (positiveRate >= 50) {
    riskColor = "#f59e0b";
    riskIcon = "⚠️";
    riskText = "trung bình";
  }

  insights.push({
    icon: "🦠",
    color: "#3b82f6",
    text: `<strong>${topDisease.disease_vi || topDisease.disease}</strong> là bệnh xuất hiện nhiều nhất, ghi nhận <strong>${topDisease.count}</strong> ca (<strong>${diseaseRate}%</strong>).`,
  });
  insights.push({
    icon: riskIcon,
    color: riskColor,
    text: `Tỷ lệ dương tính là <strong>${positiveRate}%</strong>, cho thấy mức độ nguy cơ <strong>${riskText}</strong>.`,
  });

  if (data.by_age_group?.length) {
    const topAge = data.by_age_group.reduce((a, b) =>
      Number(a.count) > Number(b.count) ? a : b,
    );
    insights.push({
      icon: "👥",
      color: "#8b5cf6",
      text: `Nhóm tuổi <strong>${topAge.age_group}</strong> có số lượng bệnh nhân cao nhất với <strong>${topAge.count}</strong> trường hợp.`,
    });
  }

  if (data.by_gender?.length) {
    const male = data.by_gender.find((g) => Number(g.gender_val) === 1);
    const maleRate = male ? (Number(male.count) / total) * 100 : 0;
    const dominant =
      maleRate >= 50
        ? `Nam giới chiếm <strong>${maleRate.toFixed(1)}%</strong>`
        : `Nữ giới chiếm <strong>${(100 - maleRate).toFixed(1)}%</strong>`;
    insights.push({
      icon: "⚧️",
      color: "#6366f1",
      text: `${dominant} tổng số bệnh nhân.`,
    });
  }

  if (data.by_bp?.length) {
    const highBP = data.by_bp.find((b) => Number(b.blood_pressure) === 2);
    if (highBP) {
      const rate = ((highBP.count / total) * 100).toFixed(1);
      insights.push({
        icon: rate >= 30 ? "💉" : "🩺",
        color: rate >= 30 ? "#ef4444" : "#10b981",
        text: `<strong>${rate}% bệnh nhân</strong> có huyết áp cao.${rate >= 30 ? " Đây là yếu tố nguy cơ đáng lưu ý." : ""}`,
      });
    }
  }

  if (data.by_cholesterol?.length) {
    const highChol = data.by_cholesterol.find((c) => c.label === "Cao");
    if (highChol) {
      const rate = ((highChol.count / total) * 100).toFixed(1);
      insights.push({
        icon: "🧪",
        color: rate >= 30 ? "#f59e0b" : "#10b981",
        text: `Cholesterol cao được ghi nhận ở <strong>${rate}% bệnh nhân</strong>.`,
      });
    }
  }

  insights.push({
    icon: "🎂",
    color: "#06b6d4",
    text: `Tuổi trung bình của bệnh nhân là <strong>${avgAge}</strong> tuổi.`,
  });

  if (data.by_symptoms?.length) {
    const topSym = data.by_symptoms.reduce((a, b) =>
      Number(a.has) > Number(b.has) ? a : b,
    );
    const symRate = ((topSym.has / total) * 100).toFixed(1);
    insights.push({
      icon: "🤒",
      color: "#ef4444",
      text: `Triệu chứng phổ biến nhất là <strong>${topSym.symptom}</strong>, xuất hiện ở <strong>${symRate}% bệnh nhân</strong>.`,
    });
  }

  if (data.positive_by_age?.length) {
    const top = data.positive_by_age.reduce((a, b) =>
      Number(a.positive_rate) > Number(b.positive_rate) ? a : b,
    );
    insights.push({
      icon: "📈",
      color: "#dc2626",
      text: `Nhóm tuổi <strong>${top.age_group}</strong> có tỷ lệ dương tính cao nhất: <strong>${Number(top.positive_rate).toFixed(1)}%</strong>.`,
    });
  }

  return insights.slice(0, 8);
}

function renderInsights(insights, containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = insights
    .map(
      (i) => `
    <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;
                background:#fff;border-radius:8px;border-left:4px solid ${i.color};
                box-shadow:0 1px 4px rgba(0,0,0,0.06);">
      <span style="font-size:1.3rem;flex-shrink:0;">${i.icon}</span>
      <p style="margin:0;font-size:0.875rem;color:#374151;line-height:1.6;">${i.text}</p>
    </div>`,
    )
    .join("");
}

function renderSingleInsight(containerId, icon, color, text) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `<div style="display:flex;gap:10px;align-items:flex-start;">
    <span style="font-size:1.2rem;">${icon}</span><div>${text}</div></div>`;
  el.style.borderLeftColor = color;
}

function generateExecutiveSummary(data) {
  const s = data.summary || {};
  const total = Number(s.total_records || 0);
  const positive = Number(s.positive_cases || 0);
  const avgAge = Number(s.avg_age || 0);

  if (!total)
    return `<h3 style="margin:0 0 8px;">📌 Thông điệp tổng quan</h3>
    <p style="margin:0;color:#6b7280;">Chưa có đủ dữ liệu.</p>`;

  const positiveRate = ((positive / total) * 100).toFixed(1);
  const topDisease = data.top_diseases?.[0];
  const topAge = data.by_age_group?.length
    ? data.by_age_group.reduce((a, b) =>
        Number(a.count) > Number(b.count) ? a : b,
      )
    : null;
  const highestPos = data.positive_by_age?.length
    ? data.positive_by_age.reduce((a, b) =>
        Number(a.positive_rate) > Number(b.positive_rate) ? a : b,
      )
    : null;

  let riskLabel = "thấp",
    riskColor = "#10b981";
  if (positiveRate >= 70) {
    riskLabel = "rất cao";
    riskColor = "#ef4444";
  } else if (positiveRate >= 50) {
    riskLabel = "trung bình";
    riskColor = "#f59e0b";
  }

  return `
    <h3 style="margin:0 0 12px;color:${riskColor};">📌 Thông điệp tổng quan</h3>
    <p style="margin:0 0 8px;line-height:1.7;color:#374151;">
      Trong tổng số <strong>${total}</strong> bệnh nhân, có <strong>${positive}</strong>
      ca dương tính (<strong>${positiveRate}%</strong>), mức độ nguy cơ <strong>${riskLabel}</strong>.
    </p>
    ${
      topDisease
        ? `<p style="margin:0 0 8px;line-height:1.7;color:#374151;">
      <strong>${topDisease.disease_vi || topDisease.disease}</strong> là bệnh phổ biến nhất với <strong>${topDisease.count}</strong> ca.</p>`
        : ""
    }
    ${
      topAge
        ? `<p style="margin:0 0 8px;line-height:1.7;color:#374151;">
      Nhóm tuổi <strong>${topAge.age_group}</strong> chiếm tỷ lệ cao nhất.</p>`
        : ""
    }
    ${
      highestPos
        ? `<p style="margin:0;line-height:1.7;color:#374151;">
      Tỷ lệ dương tính cao nhất ở nhóm <strong>${highestPos.age_group}</strong>
      (${Number(highestPos.positive_rate).toFixed(1)}%).</p>`
        : ""
    }
    <div style="margin-top:12px;padding:10px 12px;background:#fff;border-radius:8px;
                font-size:0.875rem;color:#1e40af;">
      💡 Tuổi trung bình: <strong>${avgAge}</strong> tuổi.
    </div>`;
}

// ── HOME STATS ────────────────────────────────────────────
async function loadHomeStats() {
  try {
    const res = await fetch(`${API}/api/stats`);
    const data = await res.json();
    const s = data.summary;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set("hs-total", s.total_records);
    set("hs-diseases", data.top_diseases.length);
    set("hs-positive", s.positive_cases);
    set("hs-age", s.avg_age);
  } catch (e) {
    ["hs-total", "hs-diseases", "hs-positive", "hs-age"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.textContent = "--";
    });
  }
}

// ── ARTICLES ─────────────────────────────────────────────
async function loadArticles(page = 1) {
  const grid = document.getElementById("articles-grid");
  const pagi = document.getElementById("articles-pagination");
  if (!grid) return;

  const articlePageUrl =
    typeof RRP_CONFIG !== "undefined"
      ? RRP_CONFIG.article_detail_url
      : "/chi-tiet-bai-viet/";

  try {
    const res = await fetch(`${API}/api/articles?page=${page}&per_page=6`);
    const data = await res.json();

    if (!data.articles?.length) {
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#9ca3af;">Chưa có bài viết nào.</div>`;
      return;
    }

    grid.innerHTML = data.articles
      .map(
        (a) => `
      <div class="article-card" onclick="window.location.href='${articlePageUrl}?id=${a.article_id}'">
        ${
          a.thumbnail
            ? `<img src="${a.thumbnail}" class="article-thumb"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
             <div class="article-thumb-placeholder" style="display:none;">🫁</div>`
            : `<div class="article-thumb-placeholder">🫁</div>`
        }
        <div class="article-body">
          <div class="article-source">${a.source || "Y tế"}</div>
          <div class="article-title">${a.title || ""}</div>
          <div class="article-desc">${a.description || ""}</div>
          <div class="article-footer">
            <span>${a.published_at || ""}</span>
            <span class="article-read-more">Đọc thêm →</span>
          </div>
        </div>
      </div>`,
      )
      .join("");

    if (pagi) {
      pagi.innerHTML = "";
      if (data.total_pages > 1) {
        for (let i = 1; i <= data.total_pages; i++) {
          const btn = document.createElement("button");
          btn.textContent = i;
          btn.style.cssText = `padding:8px 14px;border-radius:6px;border:1px solid #e5e7eb;
            cursor:pointer;font-size:0.875rem;margin:0 2px;
            background:${i === page ? "#3b82f6" : "#fff"};
            color:${i === page ? "#fff" : "#374151"};`;
          btn.onclick = () => {
            loadArticles(i);
            grid.scrollIntoView({ behavior: "smooth" });
          };
          pagi.appendChild(btn);
        }
      }
    }
  } catch (e) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#9ca3af;">Không thể tải bài viết.</div>`;
  }
}

// ── DASHBOARD ─────────────────────────────────────────────
async function loadDashboard() {
  try {
    const res = await fetch(`${API}/api/stats`);
    const data = await res.json();
    const s = data.summary;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    set("stat-total", s.total_records);
    set("stat-positive", s.positive_cases);
    set("stat-negative", s.negative_cases);
    set("stat-age", s.avg_age);

    // Executive summary
    const summaryEl = document.getElementById("dashboard-summary");
    if (summaryEl) summaryEl.innerHTML = generateExecutiveSummary(data);

    // Single insights
    try {
      const top = data.top_diseases?.[0];
      const diseaseRate = top
        ? ((top.count / s.total_records) * 100).toFixed(1)
        : "0.0";
      const male = data.by_gender?.find((g) => Number(g.gender_val) === 1);
      const maleRate = male
        ? ((Number(male.count) / s.total_records) * 100).toFixed(1)
        : "0.0";
      const highBP = data.by_bp?.find((b) => Number(b.blood_pressure) === 2);
      const highBPRate = highBP
        ? ((highBP.count / s.total_records) * 100).toFixed(1)
        : "0.0";
      const highChol = data.by_cholesterol?.find((c) => c.label === "Cao");
      const cholRate = highChol
        ? ((highChol.count / s.total_records) * 100).toFixed(1)
        : "0.0";
      const topSym = data.by_symptoms?.reduce((a, b) =>
        Number(a.has) > Number(b.has) ? a : b,
      );
      const topSymName = topSym?.symptom || "Không xác định";
      const posRate = ((s.positive_cases / s.total_records) * 100).toFixed(1);

      if (top)
        renderSingleInsight(
          "insight-diseases",
          "🦠",
          "#3b82f6",
          `<strong>${top.disease_vi || top.disease}</strong> là bệnh phổ biến nhất với <strong>${top.count}</strong> ca (${diseaseRate}%).`,
        );

      renderSingleInsight(
        "insight-gender",
        "⚧",
        "#8b5cf6",
        `Nam giới chiếm <strong>${maleRate}%</strong> tổng số bệnh nhân.`,
      );

      if (data.by_age_group?.length) {
        const topAgeGrp = data.by_age_group.reduce((a, b) =>
          Number(a.count) > Number(b.count) ? a : b,
        );
        renderSingleInsight(
          "insight-age-hist",
          "👥",
          "#8b5cf6",
          `Nhóm tuổi <strong>${topAgeGrp.age_group}</strong> có số lượng bệnh nhân cao nhất với <strong>${topAgeGrp.count}</strong> trường hợp.`,
        );
      }

      const ageStats = data.age_stats || data.age_summary;
      if (ageStats)
        renderSingleInsight(
          "insight-age-stats",
          "🎂",
          "#06b6d4",
          `Tuổi TB là <strong>${ageStats.mean}</strong>, trung vị <strong>${ageStats.median}</strong>, từ <strong>${ageStats.min}</strong> đến <strong>${ageStats.max}</strong>.`,
        );

      if (data.positive_by_age?.length) {
        const hp = data.positive_by_age.reduce((a, b) =>
          Number(a.positive_rate) > Number(b.positive_rate) ? a : b,
        );
        renderSingleInsight(
          "insight-positive-age",
          "📈",
          "#dc2626",
          `Nhóm tuổi <strong>${hp.age_group}</strong> có tỷ lệ dương tính cao nhất: <strong>${Number(hp.positive_rate).toFixed(1)}%</strong>.`,
        );
      }

      renderSingleInsight(
        "insight-outcome",
        posRate >= 50 ? "🚨" : "✅",
        posRate >= 50 ? "#ef4444" : "#10b981",
        `Trong <strong>${s.total_records}</strong> mẫu, có <strong>${s.positive_cases}</strong> ca dương tính (<strong>${posRate}%</strong>).`,
      );
      renderSingleInsight(
        "insight-bp",
        "💉",
        "#ef4444",
        `<strong>${highBPRate}%</strong> bệnh nhân có huyết áp cao.`,
      );
      renderSingleInsight(
        "insight-cholesterol",
        "🧪",
        "#f59e0b",
        `Cholesterol cao ghi nhận ở <strong>${cholRate}%</strong> bệnh nhân.`,
      );
      renderSingleInsight(
        "insight-symptoms",
        "🤒",
        "#ef4444",
        `Triệu chứng phổ biến nhất là <strong>${topSymName}</strong>.`,
      );
    } catch (err) {
      console.error("Insight error:", err);
    }

    // Age stats table
    const ageStats = data.age_stats || data.age_summary;
    if (ageStats) {
      const setText = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = v ?? "—";
      };
      setText("desc-count", ageStats.count);
      setText("desc-mean", ageStats.mean);
      setText("desc-std", ageStats.std);
      setText("desc-min", ageStats.min);
      setText("desc-median", ageStats.median);
      setText("desc-max", ageStats.max);
    }

    const PALETTE = [
      "#3b82f6",
      "#10b981",
      "#f59e0b",
      "#ef4444",
      "#8b5cf6",
      "#06b6d4",
      "#ec4899",
      "#84cc16",
      "#f97316",
      "#6366f1",
    ];

    // Charts
    const c1 = document.getElementById("chart-diseases");
    if (c1 && data.top_diseases?.length)
      new Chart(c1, {
        type: "bar",
        data: {
          labels: data.top_diseases.map((d) => d.disease_vi || d.disease),
          datasets: [
            {
              label: "Số ca",
              data: data.top_diseases.map((d) => d.count),
              backgroundColor: PALETTE,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { x: { beginAtZero: true } },
        },
      });

    const c2 = document.getElementById("chart-gender");
    if (c2 && data.by_gender?.length)
      new Chart(c2, {
        type: "doughnut",
        data: {
          labels: data.by_gender.map((d) =>
            d.gender_val === 1 ? "Nam" : "Nữ",
          ),
          datasets: [
            {
              data: data.by_gender.map((d) => d.count),
              backgroundColor: ["#3b82f6", "#ec4899"],
            },
          ],
        },
        options: { responsive: true },
      });

    const c3 = document.getElementById("chart-age");
    if (c3 && data.by_age_group?.length)
      new Chart(c3, {
        type: "doughnut",
        data: {
          labels: data.by_age_group.map((d) => d.age_group),
          datasets: [
            {
              data: data.by_age_group.map((d) => d.count),
              backgroundColor: PALETTE,
            },
          ],
        },
        options: { responsive: true },
      });

    const bpLabel = { 0: "Thấp", 1: "Bình thường", 2: "Cao" };
    const c4 = document.getElementById("chart-bp");
    if (c4 && data.by_bp?.length)
      new Chart(c4, {
        type: "bar",
        data: {
          labels: data.by_bp.map(
            (d) => bpLabel[d.blood_pressure] ?? d.blood_pressure,
          ),
          datasets: [
            {
              label: "Số ca",
              data: data.by_bp.map((d) => d.count),
              backgroundColor: ["#10b981", "#f59e0b", "#ef4444"],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } },
        },
      });

    const c5 = document.getElementById("chart-outcome");
    if (c5)
      new Chart(c5, {
        type: "pie",
        data: {
          labels: ["Dương tính", "Âm tính"],
          datasets: [
            {
              data: [s.positive_cases, s.negative_cases],
              backgroundColor: ["#ef4444", "#10b981"],
            },
          ],
        },
        options: { responsive: true },
      });

    const c6 = document.getElementById("chart-symptoms");
    if (c6)
      new Chart(c6, {
        type: "bar",
        data: {
          labels: ["Sốt", "Ho", "Mệt mỏi", "Khó thở"],
          datasets: [
            {
              label: "Có triệu chứng",
              data: data.by_symptoms?.map((d) => d.has) ?? [0, 0, 0, 0],
              backgroundColor: "rgba(239,68,68,0.75)",
            },
            {
              label: "Không có triệu chứng",
              data: data.by_symptoms?.map((d) => d.has_not) ?? [0, 0, 0, 0],
              backgroundColor: "rgba(34,197,94,0.75)",
            },
          ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
      });

    const cChol = document.getElementById("chart-cholesterol");
    if (cChol && data.by_cholesterol?.length)
      new Chart(cChol, {
        type: "pie",
        data: {
          labels: data.by_cholesterol.map((d) => d.label),
          datasets: [
            {
              data: data.by_cholesterol.map((d) => d.count),
              backgroundColor: ["#10b981", "#f59e0b", "#ef4444"],
            },
          ],
        },
        options: { responsive: true },
      });

    const cPosAge = document.getElementById("chart-positive-age");
    if (cPosAge && data.positive_by_age?.length)
      new Chart(cPosAge, {
        type: "bar",
        data: {
          labels: data.positive_by_age.map((d) => d.age_group),
          datasets: [
            {
              label: "Tỷ lệ dương tính (%)",
              data: data.positive_by_age.map((d) =>
                Number(d.positive_rate).toFixed(1),
              ),
              backgroundColor: "#ef4444",
            },
          ],
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });

    const cHist = document.getElementById("chart-age-hist");
    if (cHist && data.by_age_group?.length)
      new Chart(cHist, {
        type: "bar",
        data: {
          labels: data.by_age_group.map((d) => d.age_group),
          datasets: [
            {
              label: "Số bệnh nhân",
              data: data.by_age_group.map((d) => d.count),
              backgroundColor: "#3b82f6",
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } },
        },
      });
  } catch (e) {
    console.error("loadDashboard:", e);
  }
}

// ── TESTS ─────────────────────────────────────────────────
async function loadTests() {
  const body = document.getElementById("test-body");
  if (!body) return;
  try {
    const res = await fetch(`${API}/api/stats/tests`);
    const data = await res.json();
    const label = {
      fever: "Sốt",
      cough: "Ho",
      fatigue: "Mệt mỏi",
      diff_breathing: "Khó thở",
      blood_pressure: "Huyết áp",
      cholesterol: "Cholesterol",
      age: "Tuổi",
    };

    body.innerHTML = data
      .map(
        (d) => `
      <tr style="border-bottom:1px solid #eee;">
        <td style="padding:10px;">${label[d.feature] || d.feature}</td>
        <td style="text-align:center;">${d.test || "—"}</td>
        <td style="text-align:center;">${d.p_value !== null && d.p_value !== undefined ? Number(d.p_value).toFixed(4) : "—"}</td>
        <td style="text-align:center;">
          ${
            d.significant
              ? '<span style="color:green;font-weight:500;">✅ Có ý nghĩa</span>'
              : '<span style="color:gray;">❌ Không có ý nghĩa</span>'
          }
        </td>
      </tr>`,
      )
      .join("");

    const sigCount = data.filter((d) => d.significant).length;
    const aucValues = data
      .filter((d) => d.roc_auc != null)
      .map((d) => Number(d.roc_auc));
    const avgAUC = aucValues.length
      ? (aucValues.reduce((a, b) => a + b, 0) / aucValues.length).toFixed(3)
      : null;

    const testInsight = document.getElementById("test-insight");
    if (testInsight)
      testInsight.innerHTML = `
      <span style="font-size:1.2rem;">🔬</span>
      <p style="margin:0;font-size:0.875rem;color:#374151;line-height:1.7;">
        <strong>${sigCount}/${data.length}</strong> yếu tố có ý nghĩa thống kê (p &lt; 0.05).
      </p>
    `;
  } catch (e) {
    console.error("loadTests:", e);
  }
}

// ── CORRELATION HEATMAP ────────────────────────────────────
async function loadCorrelationHeatmap() {
  const canvas = document.getElementById("chart-correlation");
  if (!canvas) return;
  try {
    const res = await fetch(`${API}/api/correlation`);
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();

    const labels = Object.keys(data).filter(
      (k) => data[k] && typeof data[k] === "object",
    );
    if (!labels.length) {
      canvas.parentElement.innerHTML =
        '<p style="color:#9ca3af;text-align:center;padding:20px;">Không có dữ liệu.</p>';
      return;
    }

    const labelMap = {
      fever: "Sốt",
      cough: "Ho",
      fatigue: "Mệt mỏi",
      diff_breathing: "Khó thở",
      age: "Tuổi",
      blood_pressure: "Huyết áp",
      cholesterol: "Cholesterol",
      outcome: "Kết quả",
      gender_val: "Giới tính",
    };
    const displayLabels = labels.map((l) => labelMap[l] || l);
    const n = labels.length;

    const PAD_LEFT = 85,
      PAD_TOP = 85,
      PAD_RIGHT = 20,
      PAD_BOTTOM = 50,
      CELL = 58;
    const W = PAD_LEFT + CELL * n + PAD_RIGHT;
    const H = PAD_TOP + CELL * n + PAD_BOTTOM;

    canvas.setAttribute("width", W);
    canvas.setAttribute("height", H);

    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, W, H);

    const matrix = labels.map((row) =>
      labels.map((col) => {
        const v = data[row]?.[col];
        return v !== undefined && v !== null && !isNaN(v) ? Number(v) : 0;
      }),
    );

    function cellColor(v) {
      const a = Math.min(Math.abs(v), 1);
      if (v >= 0) {
        const gb = Math.round(255 * (1 - a * 0.85));
        return `rgb(255,${gb},${gb})`;
      } else {
        const rb = Math.round(255 * (1 - a * 0.85));
        return `rgb(${rb},${rb},255)`;
      }
    }

    for (let row = 0; row < n; row++) {
      for (let col = 0; col < n; col++) {
        const v = matrix[row][col];
        const x = PAD_LEFT + col * CELL,
          y = PAD_TOP + row * CELL;
        ctx.fillStyle = cellColor(v);
        ctx.fillRect(x, y, CELL, CELL);
        ctx.strokeStyle = "#d1d5db";
        ctx.lineWidth = 0.5;
        ctx.strokeRect(x, y, CELL, CELL);
        ctx.fillStyle = Math.abs(v) > 0.45 ? "#ffffff" : "#1f2937";
        ctx.font = "bold 11px Arial, sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(v.toFixed(2), x + CELL / 2, y + CELL / 2);
      }
    }

    ctx.fillStyle = "#374151";
    ctx.font = "12px Arial, sans-serif";
    ctx.textAlign = "left";
    ctx.textBaseline = "bottom";
    for (let col = 0; col < n; col++) {
      const cx = PAD_LEFT + col * CELL + CELL / 2;
      ctx.save();
      ctx.translate(cx, PAD_TOP - 8);
      ctx.rotate(-Math.PI / 4);
      ctx.fillText(displayLabels[col], 0, 0);
      ctx.restore();
    }

    ctx.textAlign = "right";
    ctx.textBaseline = "middle";
    ctx.font = "12px Arial, sans-serif";
    for (let row = 0; row < n; row++) {
      ctx.fillText(
        displayLabels[row],
        PAD_LEFT - 8,
        PAD_TOP + row * CELL + CELL / 2,
      );
    }

    const lgX = PAD_LEFT,
      lgY = PAD_TOP + CELL * n + 14,
      lgW = CELL * n,
      lgH = 10;
    const grad = ctx.createLinearGradient(lgX, 0, lgX + lgW, 0);
    grad.addColorStop(0, "rgb(0,0,255)");
    grad.addColorStop(0.5, "rgb(255,255,255)");
    grad.addColorStop(1, "rgb(255,0,0)");
    ctx.fillStyle = grad;
    ctx.fillRect(lgX, lgY, lgW, lgH);
    ctx.strokeStyle = "#d1d5db";
    ctx.lineWidth = 1;
    ctx.strokeRect(lgX, lgY, lgW, lgH);
    ctx.fillStyle = "#6b7280";
    ctx.font = "10px Arial, sans-serif";
    ctx.textBaseline = "top";
    ctx.textAlign = "left";
    ctx.fillText("-1", lgX, lgY + lgH + 3);
    ctx.textAlign = "center";
    ctx.fillText("0", lgX + lgW / 2, lgY + lgH + 3);
    ctx.textAlign = "right";
    ctx.fillText("+1", lgX + lgW, lgY + lgH + 3);
  } catch (err) {
    console.error("loadCorrelationHeatmap:", err);
    const wrap = canvas?.parentElement;
    if (wrap)
      wrap.innerHTML = `<div style="padding:16px;background:#fee2e2;border-radius:8px;color:#991b1b;font-size:0.875rem;">❌ Không tải được ma trận tương quan: ${err.message}</div>`;
  }
}

// ── MODELS ────────────────────────────────────────────────
async function loadModels() {
  const tbody = document.getElementById("table-models");
  if (!tbody) return;
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 8000);
    const res = await fetch(`${API}/api/models`, { signal: controller.signal });
    clearTimeout(timer);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const raw = await res.json();
    if (!Array.isArray(raw))
      throw new Error(raw.error || "API không trả về danh sách model");
    const models = raw;

    if (!models.length) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:#9ca3af;">
        ⚠️ Chưa có model nào. Hãy chạy <code>python model.py</code>.</td></tr>`;
      return;
    }

    tbody.innerHTML = models
      .map(
        (m) => `
      <tr style="border-bottom:1px solid #f3f4f6;">
        <td class="rrp-td"><strong>${m.model_name || "—"}</strong></td>
        <td class="rrp-td">${m.version || "—"}</td>
        <td class="rrp-td">${m.algorithm || "—"}</td>
        <td class="rrp-td">${m.target === "outcome" ? "🎯 Kết quả" : "🏥 Tên bệnh"}</td>
        <td class="rrp-td">${((Number(m.accuracy) || 0) * 100).toFixed(1)}%</td>
        <td class="rrp-td">${((Number(m.f1_score) || 0) * 100).toFixed(1)}%</td>
        <td class="rrp-td">${((Number(m.precision_score) || 0) * 100).toFixed(1)}%</td>
        <td class="rrp-td">${((Number(m.recall_score) || 0) * 100).toFixed(1)}%</td>
        <td class="rrp-td">
          <span style="padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;
            background:${m.status === "active" ? "#d1fae5" : "#f3f4f6"};
            color:${m.status === "active" ? "#065f46" : "#6b7280"};">
            ${m.status === "active" ? "✅ Active" : "⏸ Inactive"}
          </span>
        </td>
      </tr>`,
      )
      .join("");

    // Insight
    const modelInsight = document.getElementById("model-insight");
    if (modelInsight) {
      const bestOut = models
        .filter((m) => m.target === "outcome")
        .sort((a, b) => {
          if (b.f1_score !== a.f1_score) return b.f1_score - a.f1_score;
          if (a.status === "active" && b.status !== "active") return -1;
          if (b.status === "active" && a.status !== "active") return 1;
          return 0;
        })[0];
      const bestDis = models
        .filter((m) => m.target === "disease")
        .sort((a, b) => {
          if (b.f1_score !== a.f1_score) return b.f1_score - a.f1_score;
          if (a.status === "active" && b.status !== "active") return -1;
          if (b.status === "active" && a.status !== "active") return 1;
          return 0;
        })[0];
      modelInsight.innerHTML = `
        <span style="font-size:1.3rem;flex-shrink:0;">🤖</span>
        <p style="margin:0;font-size:0.875rem;color:#374151;line-height:1.7;">
          ${bestOut ? `<strong>Outcome tốt nhất:</strong> ${bestOut.model_name} — Accuracy <strong>${(bestOut.accuracy * 100).toFixed(1)}%</strong>, F1 <strong>${(bestOut.f1_score * 100).toFixed(1)}%</strong> ${bestOut.status === "active" ? '<span style="color:#10b981;">(✅ Active)</span>' : '<span style="color:#9ca3af;">(Inactive)</span>'}<br>` : ""}
          ${bestDis ? `<strong>Disease tốt nhất:</strong> ${bestDis.model_name} — Accuracy <strong>${(bestDis.accuracy * 100).toFixed(1)}%</strong>, F1 <strong>${(bestDis.f1_score * 100).toFixed(1)}%</strong> ${bestDis.status === "active" ? '<span style="color:#10b981;">(✅ Active)</span>' : '<span style="color:#9ca3af;">(Inactive)</span>'}` : ""}
        </p>`;
    }

    // Charts grouped
    function makeGroupedChart(canvasId, metricKey, title) {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      if (canvas._chart) {
        canvas._chart.destroy();
        canvas._chart = null;
      }
      const allAlgos = [...new Set(models.map((m) => m.algorithm))];
      const outModels = models.filter((m) => m.target === "outcome");
      const disModels = models.filter((m) => m.target === "disease");
      canvas._chart = new Chart(canvas, {
        type: "bar",
        data: {
          labels: allAlgos,
          datasets: [
            {
              label: `Outcome ${title} (%)`,
              backgroundColor: "#3b82f6",
              borderRadius: 5,
              data: allAlgos.map((a) => {
                const m = outModels.find((x) => x.algorithm === a);
                return m
                  ? ((Number(m[metricKey]) || 0) * 100).toFixed(1)
                  : null;
              }),
            },
            {
              label: `Disease ${title} (%)`,
              backgroundColor: "#10b981",
              borderRadius: 5,
              data: allAlgos.map((a) => {
                const m = disModels.find((x) => x.algorithm === a);
                return m
                  ? ((Number(m[metricKey]) || 0) * 100).toFixed(1)
                  : null;
              }),
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } },
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
              ticks: { callback: (v) => v + "%" },
            },
          },
        },
      });
    }
    makeGroupedChart("chart-model-accuracy", "accuracy", "Accuracy");
    makeGroupedChart("chart-model-f1", "f1_score", "F1 Score");

    // ROC Curve
    const rocCanvas = document.getElementById("chart-roc");
    if (rocCanvas) {
      try {
        const rocRes = await fetch(`${API}/api/roc`);
        if (!rocRes.ok) throw new Error("ROC API lỗi: " + rocRes.status);
        const rocData = await rocRes.json();

        if (!Array.isArray(rocData) || !rocData.length) {
          rocCanvas.parentElement.querySelector("div").textContent =
            "⚠️ Chưa có dữ liệu ROC.";
        } else {
          const PALETTE = [
            "#ef4444",
            "#3b82f6",
            "#10b981",
            "#f59e0b",
            "#8b5cf6",
          ];

          // Chỉ lấy model đang Active
          const activeModels = models
            .filter((m) => m.status === "active")
            .map((m) => ({
              model_name: m.algorithm,
              target: m.target,
            }));

          const filteredRoc = rocData.filter((r) =>
            activeModels.some((m) => {
              if (m.model_name !== r.model_name) return false;

              if (m.target === "outcome" && r.target === "outcome") return true;

              if (m.target === "disease" && r.target.startsWith("disease"))
                return true;

              return false;
            }),
          );
          // Tách theo model + target
          const modelNames = [
            ...new Set(filteredRoc.map((r) => `${r.model_name} (${r.target})`)),
          ];

          const datasets = modelNames.map((label, idx) => {
            const [mname, tgt] = label.split(" (");
            const target = tgt.replace(")", "");

            const pts = filteredRoc
              .filter((r) => r.model_name === mname && r.target === target)
              .sort((a, b) => Number(a.fpr) - Number(b.fpr));

            let auc = 0;
            for (let i = 1; i < pts.length; i++) {
              auc +=
                ((Number(pts[i].fpr) - Number(pts[i - 1].fpr)) *
                  (Number(pts[i].tpr) + Number(pts[i - 1].tpr))) /
                2;
            }

            return {
              label: `${mname} ${target} (AUC = ${auc.toFixed(3)})`,
              data: pts.map((p) => ({
                x: Number(p.fpr),
                y: Number(p.tpr),
              })),
              borderColor: PALETTE[idx % PALETTE.length],
              backgroundColor: "transparent",
              borderWidth: 2,
              tension: 0.2,
              fill: false,
              pointRadius: 0,
            };
          });

          datasets.push({
            label: "Random baseline (AUC = 0.500)",
            data: [
              { x: 0, y: 0 },
              { x: 1, y: 1 },
            ],
            borderColor: "#d1d5db",
            borderDash: [6, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
          });

          if (rocCanvas._chart) rocCanvas._chart.destroy();
          rocCanvas._chart = new Chart(rocCanvas, {
            type: "line",
            data: { datasets },
            options: {
              responsive: true,
              parsing: false,
              plugins: {
                legend: { position: "bottom", labels: { font: { size: 11 } } },
              },
              scales: {
                x: {
                  type: "linear",
                  min: 0,
                  max: 1,
                  title: { display: true, text: "False Positive Rate (FPR)" },
                  ticks: { stepSize: 0.2 },
                  grid: { color: "#f3f4f6" },
                },
                y: {
                  min: 0,
                  max: 1,
                  title: { display: true, text: "True Positive Rate (TPR)" },
                  ticks: { stepSize: 0.2 },
                  grid: { color: "#f3f4f6" },
                },
              },
            },
          });
        }
      } catch (rocErr) {
        console.warn("ROC error:", rocErr.message);
      }
    }
  } catch (err) {
    console.error("loadModels error:", err);
    const tb = document.getElementById("table-models");
    if (tb)
      tb.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;">
      <div style="color:#ef4444;font-weight:600;">❌ Lỗi: ${err.message}</div>
      <div style="color:#9ca3af;font-size:0.8rem;margin-top:8px;">Kiểm tra Flask đang chạy tại <code>${API}</code></div>
    </td></tr>`;
  }
}

// ── UPLOAD ────────────────────────────────────────────────
let selectedFile = null;
let vname = "";

function handleDrop(event) {
  event.preventDefault();
  const zone = document.getElementById("upload-zone");
  if (zone) {
    zone.style.borderColor = "#e5e7eb";
    zone.style.background = "";
  }
  const file = event.dataTransfer.files[0];
  if (file) handleFileSelect(file);
}

function handleFileSelect(file) {
  if (!file) return;
  const ext = "." + file.name.split(".").pop().toLowerCase();
  if (![".csv", ".xlsx", ".xls"].includes(ext)) {
    alert("Chỉ hỗ trợ CSV hoặc Excel (.xlsx, .xls)");
    return;
  }
  const now = new Date();
  vname = `Upload ${now.toLocaleDateString("vi-VN")} ${now.toLocaleTimeString("vi-VN")}`;
  selectedFile = file;
  previewFile(file);
}

async function previewFile(file) {
  const zone = document.getElementById("upload-zone");
  if (zone)
    zone.innerHTML = `<div style="font-size:2rem;margin-bottom:8px;">⏳</div><p style="color:#6b7280;">Đang phân tích file...</p>`;

  const formData = new FormData();
  formData.append("file", file);

  try {
    const res = await fetch(`${API}/api/dataset/preview`, {
      method: "POST",
      body: formData,
    });
    const data = await res.json();

    if (data.error) {
      resetUploadZone();
      alert("❌ Lỗi: " + data.error);
      return;
    }

    document.getElementById("upload-zone").style.display = "none";
    document.getElementById("upload-preview").style.display = "block";
    document.getElementById("file-info-name").textContent = `📄 ${file.name}`;
    document.getElementById("file-info-rows").textContent =
      `${data.rows} hàng hợp lệ · ${Object.keys(data.col_mapping || {}).length} cột phát hiện`;

    const colMapping = data.col_mapping || {};
    const mapped = Object.entries(colMapping).filter(
      ([, v]) => v?.status === "ok",
    );
    const skipped = Object.entries(colMapping).filter(
      ([, v]) => v?.status !== "ok",
    );

    document.getElementById("col-mapping-table").innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px 12px;font-size:0.82rem;align-items:center;">
        <div style="font-weight:600;color:#6b7280;padding:4px 0;border-bottom:1px solid #e5e7eb;">Cột gốc</div>
        <div style="font-weight:600;color:#6b7280;padding:4px 0;border-bottom:1px solid #e5e7eb;">→ Cột chuẩn</div>
        <div style="font-weight:600;color:#6b7280;padding:4px 0;border-bottom:1px solid #e5e7eb;">Trạng thái</div>
        ${mapped
          .map(
            ([orig, info]) => `
          <div style="padding:4px 0;font-family:monospace;">${orig}</div>
          <div style="padding:4px 0;font-family:monospace;color:#1e40af;font-weight:600;">${info.mapped_to || "—"}</div>
          <div style="padding:4px 0;"><span style="color:#10b981;font-weight:600;">✅ Ánh xạ OK</span></div>`,
          )
          .join("")}
        ${skipped
          .map(
            ([orig]) => `
          <div style="padding:4px 0;font-family:monospace;color:#9ca3af;">${orig}</div>
          <div style="padding:4px 0;color:#9ca3af;">—</div>
          <div style="padding:4px 0;"><span style="color:#d1d5db;">⏭ Bỏ qua</span></div>`,
          )
          .join("")}
      </div>
      <div style="margin-top:10px;font-size:0.8rem;color:#6b7280;">✅ ${mapped.length} cột ánh xạ · ⏭ ${skipped.length} cột bỏ qua</div>`;

    const warnEl = document.getElementById("upload-warnings");
    warnEl.innerHTML = data.warnings?.length
      ? data.warnings
          .map(
            (w) =>
              `<div style="padding:8px 12px;background:#fef3c7;border-radius:6px;font-size:0.82rem;color:#92400e;margin-bottom:6px;border-left:3px solid #f59e0b;">⚠️ ${w}</div>`,
          )
          .join("")
      : "";

    if (data.preview?.length) {
      const cols = Object.keys(data.preview[0]);
      const table = document.getElementById("preview-table");
      table.innerHTML = `
        <thead><tr style="background:#f9fafb;">${cols.map((c) => `<th class="rrp-th" style="white-space:nowrap;">${c}</th>`).join("")}</tr></thead>
        <tbody>${data.preview.map((row) => `<tr style="border-bottom:1px solid #f3f4f6;">${cols.map((c) => `<td class="rrp-td">${row[c] ?? "—"}</td>`).join("")}</tr>`).join("")}</tbody>`;
    }
  } catch (e) {
    resetUploadZone();
    alert("❌ Không thể kết nối Flask API: " + e.message);
  }
}

function resetUploadZone() {
  const zone = document.getElementById("upload-zone");
  if (!zone) return;
  zone.style.display = "block";
  zone.innerHTML = `
    <div style="font-size:3rem;margin-bottom:12px;">📂</div>
    <p style="margin:0;color:#374151;font-size:1rem;font-weight:500;">
      Kéo thả file vào đây hoặc <span style="color:#3b82f6;font-weight:600;">click để chọn</span>
    </p>
    <p style="margin:8px 0 0;color:#9ca3af;font-size:0.85rem;">Hỗ trợ: CSV, XLSX, XLS</p>
    <input type="file" id="file-input" accept=".csv,.xlsx,.xls"
           style="display:none;" onchange="handleFileSelect(this.files[0])">`;
}

async function confirmUpload() {
  if (!selectedFile) return;
  const vdesc = document.getElementById("version-desc")?.value?.trim() || "";
  const vname_input =
    document.getElementById("version-name")?.value?.trim() || vname;
  const mode =
    document.querySelector('input[name="import-mode"]:checked')?.value ||
    "append";

  if (
    mode === "replace" &&
    !confirm("⚠️ Xác nhận XÓA TOÀN BỘ dữ liệu upload cũ?")
  )
    return;

  const btn = document.getElementById("btn-confirm-upload");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "⏳ Đang xử lý...";
  }

  const resultEl = document.getElementById("upload-result");
  resultEl.innerHTML = `<div style="padding:14px 18px;background:#eff6ff;border-radius:8px;color:#1e40af;display:flex;align-items:center;gap:10px;"><span>⏳</span><span>Đang tải lên và xử lý dữ liệu...</span></div>`;

  const formData = new FormData();
  formData.append("file", selectedFile);
  formData.append("mode", mode);
  formData.append("version_name", vname_input);
  formData.append("description", vdesc);

  try {
    const res = await fetch(`${API}/api/dataset/upload`, {
      method: "POST",
      body: formData,
    });
    const data = await res.json();

    if (data.error) {
      resultEl.innerHTML = `<div style="padding:14px;background:#fee2e2;border-radius:8px;color:#991b1b;border-left:4px solid #ef4444;">❌ <strong>Lỗi:</strong> ${data.error}</div>`;
      return;
    }

    const warnHtml = data.warnings?.length
      ? `<div style="margin-top:10px;">${data.warnings.map((w) => `<div style="padding:6px 10px;background:#fef3c7;border-radius:4px;font-size:0.8rem;color:#92400e;margin-bottom:4px;">⚠️ ${w}</div>`).join("")}</div>`
      : "";

    resultEl.innerHTML = `
      <div style="padding:16px 18px;background:#d1fae5;border-radius:10px;color:#065f46;border-left:4px solid #10b981;">
        ✅ <strong>Nhập dữ liệu thành công!</strong><br>
        <span style="font-size:0.9rem;margin-top:4px;display:block;">
          Đã thêm <strong>${data.rows_added}</strong> hàng · Tổng: <strong>${data.total_rows}</strong> hàng · Version: <strong>#${data.version || "?"}</strong>
        </span>
      </div>
      ${warnHtml}
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
        <button onclick="resetUpload()" style="padding:9px 20px;background:#3b82f6;color:#fff;border:none;border-radius:7px;cursor:pointer;font-weight:600;">📥 Nhập file khác</button>
        <button onclick="loadVersions()" style="padding:9px 20px;background:#f3f4f6;color:#374151;border:none;border-radius:7px;cursor:pointer;">📋 Xem lịch sử versions</button>
      </div>`;

    loadVersions();
  } catch (e) {
    resultEl.innerHTML = `<div style="padding:14px;background:#fee2e2;border-radius:8px;color:#991b1b;">❌ Không thể kết nối Flask API: ${e.message}</div>`;
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "✅ Xác nhận nhập dữ liệu";
    }
  }
}

function resetUpload() {
  selectedFile = null;
  vname = "";
  resetUploadZone();
  document.getElementById("upload-preview").style.display = "none";
  document.getElementById("upload-zone").style.display = "block";
  const fi = document.getElementById("file-input");
  if (fi) fi.value = "";
  const ri = document.getElementById("upload-result");
  if (ri) ri.innerHTML = "";
}

async function loadVersions() {
  const tbody = document.getElementById("versions-body");
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:16px;color:#9ca3af;">Đang tải...</td></tr>`;
  try {
    const res = await fetch(`${API}/api/dataset/versions`);
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();

    if (!Array.isArray(data) || !data.length) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">Chưa có dữ liệu nào được upload.</td></tr>`;
      return;
    }

    const statusLabel = {
      pending:
        '<span style="color:#f59e0b;font-weight:600;">⏳ Chờ xử lý</span>',
      trained:
        '<span style="color:#10b981;font-weight:600;">✅ Đã train</span>',
      active: '<span style="color:#3b82f6;font-weight:600;">🚀 Active</span>',
    };

    tbody.innerHTML = data
      .map(
        (v) => `
      <tr style="border-bottom:1px solid #f3f4f6;">
        <td class="rrp-td" style="font-weight:600;color:#3b82f6;">#${v.data_version}</td>
        <td class="rrp-td">${v.version_name || "—"}</td>
        <td class="rrp-td">${v.rows_added ?? "—"}</td>
        <td class="rrp-td" style="color:#6b7280;font-size:0.85rem;">${(v.created_at || "").toString().substring(0, 16).replace("T", " ")}</td>
        <td class="rrp-td">${statusLabel[v.model_status] || `<span style="color:#6b7280;">${v.model_status || "—"}</span>`}</td>
      </tr>`,
      )
      .join("");
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:16px;color:#ef4444;">❌ ${e.message}</td></tr>`;
  }
}

window.handleDrop = handleDrop;
window.handleFileSelect = handleFileSelect;
window.confirmUpload = confirmUpload;
window.resetUpload = resetUpload;
window.loadVersions = loadVersions;

// ── CONFIG ────────────────────────────────────────────────
async function loadConfig() {
  try {
    const res = await fetch(`${API}/api/config`);
    const cfg = await res.json();
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.value = val;
    };
    const setTxt = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set("cfg-threshold-low", cfg.threshold_low);
    set("cfg-threshold-high", cfg.threshold_high);
    set("cfg-top-k", cfg.top_k_disease);
    set("cfg-icd-threshold", cfg.icd_threshold);
    const cb = document.getElementById("cfg-chatbot");
    if (cb) {
      cb.checked = cfg.chatbot_status === 1;
      updateToggle(cb.checked);
      cb.addEventListener("change", () => updateToggle(cb.checked));
    }
    setTxt("cur-low", cfg.threshold_low);
    setTxt("cur-high", cfg.threshold_high);
    setTxt("cur-topk", cfg.top_k_disease);
    setTxt("cur-chatbot", cfg.chatbot_status ? "🟢 Bật" : "🔴 Tắt");
  } catch (e) {
    console.error("loadConfig:", e);
  }
}

function updateToggle(checked) {
  const slider = document.getElementById("toggle-slider");
  const label = document.getElementById("chatbot-status-label");
  if (slider) slider.style.background = checked ? "#3b82f6" : "#ccc";
  if (label) label.textContent = checked ? "🟢 Đang bật" : "🔴 Đang tắt";
}

async function saveConfig() {
  const g = (id) => document.getElementById(id);
  const payload = {
    threshold_low: parseFloat(g("cfg-threshold-low").value),
    threshold_high: parseFloat(g("cfg-threshold-high").value),
    top_k_disease: parseInt(g("cfg-top-k").value),
    chatbot_status: g("cfg-chatbot").checked ? 1 : 0,
  };
  const res = await fetch(`${API}/api/config`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  const msg = document.getElementById("config-msg");
  if (msg) {
    msg.style.color = data.status === "ok" ? "green" : "red";
    msg.textContent = data.status === "ok" ? "✅ Đã lưu!" : "❌ Lỗi!";
    setTimeout(() => (msg.textContent = ""), 3000);
  }
  if (data.status === "ok") loadConfig();
}

// ── CODES ─────────────────────────────────────────────────
async function loadCodes() {
  const tbody = document.getElementById("codes-body");
  if (!tbody) return;
  const roleLabel = {
    medical_staff: "👨‍⚕️ Cán bộ y tế",
    researcher: "🔬 Nhà nghiên cứu",
    rrp_admin: "⚙️ Quản trị RRP",
  };
  try {
    const res = await fetch(`${API}/api/activation-codes`);
    const codes = await res.json();
    tbody.innerHTML = codes
      .map(
        (c) => `
      <tr>
        <td class="rrp-td"><strong style="font-family:monospace;font-size:1rem;color:#1e40af;">${c.code}</strong></td>
        <td class="rrp-td">${roleLabel[c.role] || c.role}</td>
        <td class="rrp-td">${c.description || "—"}</td>
        <td class="rrp-td"><span class="${c.is_active ? "badge-active" : "badge-inactive"}">${c.is_active ? "✅ Đang dùng" : "⏸ Tắt"}</span></td>
        <td class="rrp-td" style="display:flex;gap:6px;">
          <button onclick="toggleCode(${c.code_id},${c.is_active ? 0 : 1})"
            style="padding:4px 10px;border-radius:4px;border:none;cursor:pointer;font-size:0.8rem;
                   background:${c.is_active ? "#fee2e2" : "#d1fae5"};color:${c.is_active ? "#991b1b" : "#065f46"};">
            ${c.is_active ? "Tắt" : "Bật"}
          </button>
          <button onclick="deleteCode(${c.code_id})" style="padding:4px 10px;border-radius:4px;border:none;cursor:pointer;font-size:0.8rem;background:#f3f4f6;color:#6b7280;">Xóa</button>
        </td>
      </tr>`,
      )
      .join("");
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">Lỗi tải dữ liệu.</td></tr>`;
  }
}

function showAddCode() {
  document.getElementById("add-code-form").style.display = "block";
  document.getElementById("new-code").focus();
}

async function saveCode() {
  const code = document.getElementById("new-code").value.trim().toUpperCase();
  const role = document.getElementById("new-role").value;
  const desc = document.getElementById("new-desc").value.trim();
  if (!code) {
    alert("Vui lòng nhập mã!");
    return;
  }
  const res = await fetch(`${API}/api/activation-codes`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ code, role, description: desc }),
  });
  const data = await res.json();
  if (data.status === "ok") {
    document.getElementById("add-code-form").style.display = "none";
    document.getElementById("new-code").value = "";
    document.getElementById("new-desc").value = "";
    loadCodes();
  } else {
    alert("Lỗi: " + (data.error || "Không thể thêm mã"));
  }
}

async function toggleCode(id, newStatus) {
  await fetch(`${API}/api/activation-codes/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ is_active: newStatus }),
  });
  loadCodes();
}

async function deleteCode(id) {
  if (!confirm("Xác nhận tắt mã này?")) return;
  await fetch(`${API}/api/activation-codes/${id}`, { method: "DELETE" });
  loadCodes();
}

// ── USERS ─────────────────────────────────────────────────
async function loadUsers() {
  const tbody = document.getElementById("users-body");
  if (!tbody) return;
  const roleLabel = {
    administrator: "🔴 Administrator",
    rrp_admin: "⚙️ Quản trị RRP",
    researcher: "🔬 Nhà nghiên cứu",
    medical_staff: "👨‍⚕️ Cán bộ y tế",
    subscriber: "👤 Người dùng",
  };
  try {
    const res = await fetch(`${API}/api/users`);
    const users = await res.json();
    tbody.innerHTML = users
      .map(
        (u) => `
      <tr>
        <td class="rrp-td">${u.full_name || "—"}</td>
        <td class="rrp-td">${u.username}</td>
        <td class="rrp-td">${u.email}</td>
        <td class="rrp-td">
          <select id="role-${u.user_id}" style="padding:4px 8px;border-radius:4px;border:1px solid #e5e7eb;font-size:0.8rem;">
            ${Object.entries(roleLabel)
              .map(
                ([val, lbl]) =>
                  `<option value="${val}" ${u.role === val ? "selected" : ""}>${lbl}</option>`,
              )
              .join("")}
          </select>
        </td>
        <td class="rrp-td">${(u.created_at || "").substring(0, 10)}</td>
        <td class="rrp-td"><span class="badge-active">✅ Hoạt động</span></td>
        <td class="rrp-td">
          <button onclick="updateRole(${u.user_id}, document.getElementById('role-${u.user_id}').value)"
            style="padding:4px 10px;background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:0.8rem;">
            Lưu
          </button>
        </td>
      </tr>`,
      )
      .join("");
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:#9ca3af;">Lỗi tải dữ liệu.</td></tr>`;
  }
}

async function updateRole(userId, newRole) {
  const res = await fetch(`${API}/api/users/${userId}/role`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ role: newRole }),
  });
  const data = await res.json();
  if (data.status === "ok") {
    alert("✅ Đã cập nhật vai trò!");
    loadUsers();
  }
}

// ── LOGS ──────────────────────────────────────────────────
async function loadLogs() {
  const tbody = document.getElementById("logs-body");
  if (!tbody) return;
  try {
    const res = await fetch(`${API}/api/logs`);
    const logs = await res.json();
    if (!logs.length) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">Chưa có nhật ký.</td></tr>`;
      return;
    }
    tbody.innerHTML = logs
      .map(
        (l) => `
      <tr>
        <td class="rrp-td">${l.created_at}</td>
        <td class="rrp-td">${l.user_id || "—"}</td>
        <td class="rrp-td"><strong>${l.action}</strong></td>
        <td class="rrp-td">${l.description || "—"}</td>
        <td class="rrp-td">${l.ip_address || "—"}</td>
      </tr>`,
      )
      .join("");
  } catch (e) {
    console.error("loadLogs:", e);
  }
}

// ── EXPOSE ────────────────────────────────────────────────
window.switchTab = switchTab;
window.saveConfig = saveConfig;
window.showAddCode = showAddCode;
window.saveCode = saveCode;
window.toggleCode = toggleCode;
window.deleteCode = deleteCode;
window.updateRole = updateRole;
