<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Knowledge Base – DTR Workbooks CRM Integration</title>
  <style>
    :root {
      --bg: #0b0f14;
      --panel: #111827;
      --panel-2: #0f172a;
      --text: #e5e7eb;
      --muted: #9ca3af;
      --brand: #60a5fa;
      --brand-2: #7dd3fc;
      --ring: rgba(96,165,250,0.35);
      --border: #1f2937;
      --radius: 16px;
    }

    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      background: radial-gradient(1200px 800px at 20% -10%, rgba(29,78,216,0.15), transparent 40%),
                  radial-gradient(1000px 700px at 110% 10%, rgba(14,165,233,0.12), transparent 45%),
                  var(--bg);
      color: var(--text);
    }

    .wrap { margin: 0; padding: 0; }

    header {
      display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom: 24px;
    }
    .title {
      font-size: clamp(28px, 4vw, 40px); font-weight: 800; letter-spacing: 0.2px;
      background: linear-gradient(90deg, var(--brand), var(--brand-2));
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .subtitle { color: var(--muted); margin-top: 4px; font-size: 14px; }

    /* Accordion */
    .kb {
      display: grid; gap: 14px;
    }

    details.kb-item {
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.00));
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: clip;
      transition: border-color .2s ease, box-shadow .2s ease, transform .04s ease;
    }

    details.kb-item[open] { box-shadow: 0 0 0 6px var(--ring); border-color: #2b384a; }

    summary.kb-summary {
      list-style: none; cursor: pointer; display: flex; align-items: center; gap: 12px;
      padding: 16px 18px; font-weight: 600; font-size: 16px; color: var(--text);
    }
    summary.kb-summary::-webkit-details-marker { display: none; }

    summary.kb-summary {
        font-size:15px !important;
        background:#eee !important;
        display:flex !important;
        gap:20px !important;
    }
    .chev {
      width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;
      border-radius: 10px; background: linear-gradient(180deg, #1f2937, #111827);
      border: 1px solid var(--border);
      transition: transform .2s ease;
      flex: 0 0 20px;
    }
    details[open] .chev { transform: rotate(90deg); }
    .summary-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .kb-content { padding: 8px 18px 18px 54px; color: var(--text); }
    .kb-content p { margin: 0 0 12px; line-height: 1.6; color: var(--text); }
    .kb-content .muted { color: var(--muted); }

    /* Step blocks */
    .step {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 12px; padding: 14px; margin: 12px 0;
    }
    .step h4 { margin: 0 0 8px; font-size: 15px; }
    .step ol, .step ul { margin: 8px 0 0 18px; }
    .step li { margin: 6px 0; }

    .callout {
      margin: 12px 0; padding: 12px 14px; border-radius: 12px;
      background: linear-gradient(180deg, rgba(96,165,250,0.15), rgba(96,165,250,0.05));
      border: 1px dashed rgba(96,165,250,0.35);
      color: var(--text);
    }

    .kbd { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; font-size: 12px; padding: 2px 6px; border: 1px solid var(--border); border-bottom-width: 2px; border-radius: 6px; background: #0b1220; color: var(--text); }

    /* FAQ styling */
    .faq-q { font-weight: 600; margin-top: 12px; }
    .faq-a { color: var(--muted); margin: 6px 0 12px; }

    /* Footer */
    footer { margin-top: 28px; color: var(--muted); font-size: 13px; }

    /* Motion preference */
    @media (prefers-reduced-motion: reduce) {
      * { transition-duration: 0.001ms !important; scroll-behavior: auto !important; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h2>Knowledge Base</h2>
    <p>DTR – Workbooks CRM Integration · User Guide for Content Editors</p>

    <section class="kb" id="kb">
      <!-- 1. Plugin Settings -->
      <details class="kb-item">
        <summary class="kb-summary"><span class="summary-text">Plugin Settings</span></summary>
        <div class="kb-content">
          <div class="step">
            <h4>Connect to Workbooks API</h4>
            <ol>
              <li>In WordPress Admin, go to <span class="kbd">Settings → DTR Workbooks CRM</span>.</li>
              <li>Enter your <strong>API URL</strong> and <strong>API Key</strong> (provided by your CRM admin).</li>
              <li>Click <strong>Save Changes</strong>.</li>
              <li>If successful, you will see a confirmation message.</li>
            </ol>
          </div>
        </div>
      </details>

      <!-- 2. Lead Generation Posts -->
      <details class="kb-item">
        <summary class="kb-summary"><span class="summary-text">Lead Generation Posts</span></summary>
        <div class="kb-content">
          <div class="step">
            <h4>Step 1: Add a New Post</h4>
            <ol>
              <li>In WordPress Admin, go to <span class="kbd">Posts → Add New</span> for the <strong>Custom Post Type</strong> you require.</li>
              <li>Enter the <strong>Post Title</strong>, <strong>Stand‑first</strong>, and <strong>Gated Preview Copy</strong>.</li>
            </ol>
          </div>

          <div class="step">
            <h4>Step 2: Enable Gated Content (optional)</h4>
            <ol>
              <li>In the <strong>Gated Content</strong> panel, toggle <strong>Gated Content = On</strong>.</li>
              <li>From the <strong>Form Dropdown</strong>, select <strong>DTR Lead Generation</strong>.</li>
              <li>Enter required info:
                <ul>
                  <li><strong>Gated Title / Message</strong> (what users see before unlocking)</li>
                  <li><strong>Download Link / Resource</strong> (if applicable)</li>
                </ul>
              </li>
            </ol>
          </div>

          <div class="step">
            <h4>Step 2.1: Add Additional Questions (optional)</h4>
            <p class="muted">This option appears after selecting the <strong>DTR Lead Generation</strong> form.</p>
            <ol>
              <li>Toggle <strong>Additional Questions = On</strong>.</li>
              <li>Use the <strong>Repeater Button</strong> to add as many questions as needed.</li>
              <li>Available types:
                <ul>
                  <li>Dropdown</li>
                  <li>Checkboxes</li>
                  <li>Radio Buttons</li>
                  <li>Text Input</li>
                  <li>Textarea</li>
                </ul>
              </li>
            </ol>
            <div class="callout">
              <strong>Per‑question guidance</strong>
              <ul>
                <li><em>Checkboxes / Radios:</em> Click <strong>Add Option</strong> and type each choice.</li>
                <li><em>Text Input:</em> Enter the <strong>Question Title</strong> (the input will display automatically).</li>
                <li><em>Textarea:</em> Enter the <strong>Question Title</strong> and leave the answer area blank.</li>
              </ul>
            </div>
          </div>

          <div class="step">
            <h4>Step 3: Complete Post Information (visible after access)</h4>
            <ul>
              <li>Summary / Description</li>
              <li>Date / Time (if event)</li>
              <li>Links</li>
              <li>Call‑to‑Action (CTA) settings</li>
            </ul>
          </div>

          <div class="step">
            <h4>Step 4: Add Media &amp; Metadata</h4>
            <ol>
              <li>Add images, banners, or featured images.</li>
              <li>Assign categories, tags, and topics.</li>
              <li>Add authors, speakers, or related metadata as required.</li>
            </ol>
          </div>

          <div class="step">
            <h4>Step 5: Save as Draft</h4>
            <ol>
              <li>Click <strong>Save Draft</strong>.</li>
              <li>Use <strong>Preview</strong> to check layout and gated form behavior.</li>
            </ol>
          </div>

          <div class="step">
            <h4>Step 6: Publish the Post</h4>
            <ol>
              <li>Once satisfied, click <strong>Publish</strong>.</li>
              <li>The post is now live and visible to users.</li>
            </ol>
            <div class="callout">
              <strong>Submission storage & CRM sync</strong><br />
              Submissions are sent automatically into <strong>Workbooks CRM</strong> along with the user’s details and specified questions. They are also stored in the <strong>Ninja Forms → Submissions</strong> tab for failover functionality.
            </div>
          </div>
        </div>
      </details>

      <!-- 3. FAQ / Troubleshooting -->
      <details class="kb-item">
        <summary class="kb-summary"><span class="summary-text">FAQ / Troubleshooting</span></summary>
        <div class="kb-content">
          <p class="faq-q">Why don’t I see the “Additional Questions” toggle?</p>
          <p class="faq-a">The toggle only appears after selecting <strong>DTR Lead Generation</strong> from the Form Dropdown in the Gated Content panel.</p>

          <p class="faq-q">Where can I view failed or all submissions?</p>
          <p class="faq-a">All submissions are stored in <strong>Ninja Forms → Submissions</strong>. This serves as a failover if data cannot be pushed to Workbooks CRM.</p>

          <p class="faq-q">My post isn’t gating content—what should I check?</p>
          <p class="faq-a">Confirm that <strong>Gated Content = On</strong> and that the <strong>DTR Lead Generation</strong> form is selected. Also ensure the post is <strong>Published</strong> and not just in <strong>Draft</strong>.</p>

          <p class="faq-q">How can I verify the API connection?</p>
          <p class="faq-a">Go to <strong>Settings → DTR Workbooks CRM</strong>. After saving API URL and API Key, a confirmation message indicates success.</p>
        </div>
      </details>

      <!-- 4. Contact Support -->
      <details class="kb-item">
        <summary class="kb-summary"><span class="summary-text">Contact Support</span></summary>
        <div class="kb-content">
          <p>If you need help, provide the following to your site administrator or CRM support:</p>
          <ul>
            <li>Post URL</li>
            <li>Date & time of the issue</li>
            <li>Brief description of the problem</li>
          </ul>
          <footer>© Supersonic Playground — DTR (Drug Target Review). All rights reserved.</footer>
        </div>
      </details>
    </section>
  </div>

  <script>
    // Optional enhancement: allow only one main section open at a time
    (function(){
      const items = Array.from(document.querySelectorAll('details.kb-item'));
      items.forEach((el) => {
        el.addEventListener('toggle', () => {
          if (el.open) {
            items.forEach((other) => {
              if (other !== el) other.open = false;
            });
          }
        });
      });
    })();
  </script>
</body>
</html>