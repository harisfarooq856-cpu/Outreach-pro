# Outreach Pro

A high-performance, automated outreach and content orchestration engine designed specifically for WordPress environments. Engineered for speed, efficiency, and minimal DOM footprint, Outreach Pro integrates advanced AI workflows, automated email queuing, live performance tracking, and document synthesis capabilities directly within a unified plugin framework.

## 🚀 Core Functionalities

- **AI-Powered Outreach Engine:** Native integration with high-velocity LLM APIs via a robust orchestration layer (`class-groq.php`) to generate context-aware, hyper-personalized communication copies and prompt matrix files.
- **Throttled Batch Mailer Architecture:** Industrial-grade mailing core backed by integrated PHPMailer handlers (`class-mailer.php`) and transactional scheduling hooks (`class-scheduler.php`). Engineered to dispatch emails in controlled micro-batches (e.g., 5 emails every 5 minutes) to mimic human velocity, respect API rate limits, and maximize SMTP deliverability.
- **PageSpeed Optimization & Audits:** Automated site performance analysis modules (`class-pagespeed.php`) designed to execute background field audits and integrate operational optimization insights.
- **Dynamic Report Synthesis:** Enterprise-grade document generation engine powered by embedded data layout utilities (`class-pdf-generator.php`) and spreadsheet connectors (`class-sheets.php`) to convert structured lead lists into client-ready deliverables.
- **Clean Architectural Overhead:** Avoids heavy third-party visualization components, opting instead for optimized PHP scripts and strict background execution workers (`uninstall.php`).

## 📁 Repository Directory Structure

```text
Outreach-pro/
├── seo-outreach-pro.php         # Primary plugin bootstrapper & global hooks layout
├── uninstall.php                # Database and option hygiene teardown handler
├── includes/                    # Deep execution logic core
│   ├── class-groq.php           # High-velocity AI inference connector
│   ├── class-mailer.php         # SMTP transaction routing controller
│   ├── class-pagespeed.php      # Automated PageSpeed Insights background runner
│   ├── class-pdf-generator.php  # Core layout generator for dynamic reports
│   ├── class-prompt.php         # Structured AI template management engine
│   ├── class-prompt.php.bak     # Legacy prompt routing matrix fallback
│   ├── class-scheduler.php      # CRON manager and async email delivery queue / batch throttling
│   ├── class-settings.php       # Operational dashboard options manager
│   └── class-sheets.php         # Google Sheets synchronization layer
└── vendor/                      # Heavy asset dependencies and rendering libraries
    ├── fpdf/                    # Low-overhead PDF layout building blocks
    └── phpmailer/               # Isolated secure transmission framework
