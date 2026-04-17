#!/bin/bash
# Usage: ./deploy_icons.sh /path/to/your/moodle
# Example: ./deploy_icons.sh /var/www/html/moodle
MOODLE_ROOT="${1:-/var/www/html/moodle}"
BASE="$MOODLE_ROOT/theme/moove/pix_plugins/mod"

icon() {
    local mod=$1; local svg=$2
    sudo mkdir -p "$BASE/$mod"
    echo "$svg" | sudo tee "$BASE/$mod/monologo.svg" > /dev/null
    sudo chown www-data:www-data "$BASE/$mod/monologo.svg"
    echo "OK $mod"
}

# ── ASSIGN — clipboard with tick ──────────────────────────────────────────────
icon assign '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="22" y="28" width="56" height="62" rx="5" fill="#FFED00"/>
<rect x="36" y="18" width="28" height="18" rx="5" fill="#FFED00"/>
<rect x="40" y="14" width="20" height="14" rx="4" fill="#16213e"/>
<rect x="30" y="46" width="40" height="6" rx="2" fill="#1a1a2e"/>
<rect x="30" y="58" width="40" height="6" rx="2" fill="#1a1a2e"/>
<polyline points="30,74 40,84 56,66" stroke="#1a1a2e" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
</svg>'

# ── ATTENDANCE — calendar with checkmark ─────────────────────────────────────
icon attendance '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="14" y="24" width="72" height="62" rx="7" fill="#FFED00"/>
<rect x="14" y="24" width="72" height="22" rx="7" fill="#FFED00"/>
<rect x="14" y="38" width="72" height="8" fill="#FFED00"/>
<rect x="14" y="24" width="72" height="20" rx="7" fill="#16213e"/>
<rect x="26" y="14" width="10" height="20" rx="4" fill="#FFED00"/>
<rect x="64" y="14" width="10" height="20" rx="4" fill="#FFED00"/>
<polyline points="30,66 42,78 68,54" stroke="#1a1a2e" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
</svg>'

# ── BIGBLUEBUTTONBN — video camera ────────────────────────────────────────────
icon bigbluebuttonbn '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="10" y="32" width="54" height="38" rx="7" fill="#FFED00"/>
<polygon points="68,38 90,26 90,74 68,62" fill="#FFED00"/>
<circle cx="37" cy="51" r="12" fill="#1a1a2e"/>
<circle cx="37" cy="51" r="6" fill="#FFED00"/>
</svg>'

# ── BOOK — open book ──────────────────────────────────────────────────────────
icon book '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M50,22 C50,22 18,16 10,24 L10,80 C18,72 50,78 50,78 L50,22Z" fill="#FFED00"/>
<path d="M50,22 C50,22 82,16 90,24 L90,80 C82,72 50,78 50,78 L50,22Z" fill="#FFED00"/>
<rect x="47" y="22" width="6" height="56" rx="2" fill="#1a1a2e"/>
<rect x="18" y="36" width="24" height="5" rx="2" fill="#1a1a2e"/>
<rect x="18" y="46" width="24" height="5" rx="2" fill="#1a1a2e"/>
<rect x="18" y="56" width="18" height="5" rx="2" fill="#1a1a2e"/>
<rect x="58" y="36" width="24" height="5" rx="2" fill="#1a1a2e"/>
<rect x="58" y="46" width="24" height="5" rx="2" fill="#1a1a2e"/>
<rect x="58" y="56" width="18" height="5" rx="2" fill="#1a1a2e"/>
</svg>'

# ── CHOICE — radio button list ────────────────────────────────────────────────
icon choice '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<circle cx="24" cy="32" r="10" fill="#FFED00"/>
<circle cx="24" cy="32" r="5" fill="#1a1a2e"/>
<circle cx="24" cy="32" r="2" fill="#FFED00"/>
<rect x="40" y="26" width="46" height="12" rx="5" fill="#FFED00"/>
<circle cx="24" cy="54" r="10" fill="#FFED00"/>
<rect x="40" y="48" width="46" height="12" rx="5" fill="#FFED00"/>
<circle cx="24" cy="76" r="10" fill="#FFED00"/>
<circle cx="24" cy="76" r="5" fill="#1a1a2e"/>
<circle cx="24" cy="76" r="2" fill="#FFED00"/>
<rect x="40" y="70" width="46" height="12" rx="5" fill="#FFED00"/>
</svg>'

# ── DATA — database ───────────────────────────────────────────────────────────
icon data '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<ellipse cx="50" cy="24" rx="32" ry="12" fill="#FFED00"/>
<rect x="18" y="24" width="64" height="16" fill="#FFED00"/>
<ellipse cx="50" cy="40" rx="32" ry="12" fill="#FFED00"/>
<rect x="18" y="40" width="64" height="16" fill="#FFED00"/>
<ellipse cx="50" cy="56" rx="32" ry="12" fill="#FFED00"/>
<rect x="18" y="56" width="64" height="14" fill="#FFED00"/>
<ellipse cx="50" cy="70" rx="32" ry="12" fill="#FFED00"/>
<ellipse cx="50" cy="24" rx="32" ry="12" fill="#16213e"/>
<ellipse cx="50" cy="24" rx="32" ry="12" fill="none" stroke="#FFED00" stroke-width="3"/>
<ellipse cx="50" cy="40" rx="32" ry="12" fill="none" stroke="#1a1a2e" stroke-width="2"/>
<ellipse cx="50" cy="56" rx="32" ry="12" fill="none" stroke="#1a1a2e" stroke-width="2"/>
</svg>'

# ── FEEDBACK — speech bubble with stars ───────────────────────────────────────
icon feedback '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M12,16 L88,16 Q94,16 94,23 L94,64 Q94,71 88,71 L54,71 L40,86 L40,71 L12,71 Q6,71 6,64 L6,23 Q6,16 12,16Z" fill="#FFED00"/>
<polygon points="28,30 31,40 42,40 33,47 36,57 28,50 20,57 23,47 14,40 25,40" fill="#1a1a2e"/>
<polygon points="60,30 63,38 71,38 65,43 67,51 60,46 53,51 55,43 49,38 57,38" fill="#1a1a2e"/>
</svg>'

# ── FOLDER ────────────────────────────────────────────────────────────────────
icon folder '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M8,36 L8,82 Q8,88 15,88 L85,88 Q92,88 92,82 L92,42 Q92,36 85,36 L50,36 L40,22 Q38,16 32,16 L15,16 Q8,16 8,22 Z" fill="#FFED00"/>
<rect x="8" y="42" width="84" height="10" rx="3" fill="#16213e" opacity="0.3"/>
</svg>'

# ── FORUM — two speech bubbles ────────────────────────────────────────────────
icon forum '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M8,12 L62,12 Q70,12 70,20 L70,48 Q70,56 62,56 L40,56 L28,70 L28,56 L8,56 Q0,56 0,48 L0,20 Q0,12 8,12Z" fill="#16213e"/>
<path d="M8,12 L62,12 Q70,12 70,20 L70,48 Q70,56 62,56 L40,56 L28,70 L28,56 L8,56 Q0,56 0,48 L0,20 Q0,12 8,12Z" fill="#FFED00" opacity="0.85"/>
<path d="M34,56 L86,56 Q94,56 94,48 L94,26 Q94,18 86,18 L70,18 L70,48 Q70,56 62,56 Z" fill="#FFED00" opacity="0.5"/>
<rect x="12" y="28" width="36" height="5" rx="2" fill="#1a1a2e"/>
<rect x="12" y="39" width="28" height="5" rx="2" fill="#1a1a2e"/>
</svg>'

# ── GLOSSARY — book with A ────────────────────────────────────────────────────
icon glossary '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="18" y="12" width="64" height="78" rx="6" fill="#FFED00"/>
<rect x="18" y="12" width="14" height="78" rx="5" fill="#16213e"/>
<path d="M50,28 L62,72 L56,72 L53,62 L47,62 L44,72 L38,72 Z M50,38 L52,56 L48,56 Z" fill="#1a1a2e"/>
</svg>'

# ── H5P — interactive layers ──────────────────────────────────────────────────
icon h5pactivity '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M50,10 L88,28 L50,46 L12,28 Z" fill="#FFED00"/>
<path d="M12,42 L50,60 L88,42 L88,52 L50,70 L12,52 Z" fill="#FFED00" opacity="0.7"/>
<path d="M12,64 L50,82 L88,64 L88,74 L50,90 L12,74 Z" fill="#FFED00" opacity="0.45"/>
</svg>'

# ── IMSCP — package ───────────────────────────────────────────────────────────
icon imscp '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M50,10 L86,28 L86,76 L50,90 L14,76 L14,28 Z" fill="#FFED00"/>
<path d="M14,28 L50,46 L86,28" stroke="#1a1a2e" stroke-width="5" fill="none"/>
<line x1="50" y1="46" x2="50" y2="90" stroke="#1a1a2e" stroke-width="5"/>
<path d="M32,20 L68,38" stroke="#1a1a2e" stroke-width="3" fill="none" stroke-dasharray="4,3"/>
</svg>'

# ── LABEL — price tag ─────────────────────────────────────────────────────────
icon label '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M16,14 L60,14 L86,40 L86,56 L48,88 L14,56 L14,18 Q14,14 16,14Z" fill="#FFED00"/>
<circle cx="32" cy="30" r="8" fill="#1a1a2e"/>
<circle cx="32" cy="30" r="4" fill="#FFED00"/>
<line x1="44" y1="52" x2="66" y2="74" stroke="#1a1a2e" stroke-width="6" stroke-linecap="round"/>
<line x1="44" y1="64" x2="56" y2="76" stroke="#1a1a2e" stroke-width="6" stroke-linecap="round"/>
</svg>'

# ── LESSON — stepping path ────────────────────────────────────────────────────
icon lesson '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<circle cx="20" cy="80" r="13" fill="#FFED00"/>
<circle cx="50" cy="52" r="13" fill="#FFED00"/>
<circle cx="80" cy="26" r="13" fill="#FFED00"/>
<line x1="20" y1="80" x2="50" y2="52" stroke="#FFED00" stroke-width="7" stroke-linecap="round"/>
<line x1="50" y1="52" x2="80" y2="26" stroke="#FFED00" stroke-width="7" stroke-linecap="round"/>
<polygon points="80,10 94,32 66,32" fill="#FFED00"/>
<circle cx="20" cy="80" r="5" fill="#1a1a2e"/>
<circle cx="50" cy="52" r="5" fill="#1a1a2e"/>
</svg>'

# ── LTI — external tool ───────────────────────────────────────────────────────
icon lti '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="12" y="28" width="50" height="50" rx="6" fill="#FFED00"/>
<rect x="20" y="36" width="34" height="34" rx="4" fill="#1a1a2e"/>
<path d="M54,20 L80,20 L80,46" stroke="#FFED00" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
<line x1="80" y1="20" x2="46" y2="54" stroke="#FFED00" stroke-width="7" stroke-linecap="round"/>
</svg>'

# ── PAGE — document ───────────────────────────────────────────────────────────
icon page '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M20,10 L66,10 L80,26 L80,90 Q80,94 76,94 L20,94 Q16,94 16,90 L16,14 Q16,10 20,10Z" fill="#FFED00"/>
<path d="M66,10 L80,26 L66,26 Z" fill="#16213e"/>
<rect x="26" y="40" width="48" height="6" rx="2" fill="#1a1a2e"/>
<rect x="26" y="52" width="48" height="6" rx="2" fill="#1a1a2e"/>
<rect x="26" y="64" width="48" height="6" rx="2" fill="#1a1a2e"/>
<rect x="26" y="76" width="32" height="6" rx="2" fill="#1a1a2e"/>
</svg>'

# ── QUIZ — question mark ──────────────────────────────────────────────────────
icon quiz '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<circle cx="50" cy="50" r="38" fill="#FFED00"/>
<path d="M36,40 Q36,22 50,22 Q64,22 64,38 Q64,50 50,54 L50,64" stroke="#1a1a2e" stroke-width="9" stroke-linecap="round" fill="none"/>
<circle cx="50" cy="76" r="5" fill="#1a1a2e"/>
</svg>'

# ── RESOURCE — file ───────────────────────────────────────────────────────────
icon resource '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M22,10 L66,10 L78,24 L78,90 Q78,94 74,94 L22,94 Q18,94 18,90 L18,14 Q18,10 22,10Z" fill="#FFED00"/>
<path d="M66,10 L78,24 L66,24 Z" fill="#16213e"/>
<rect x="28" y="38" width="44" height="6" rx="2" fill="#1a1a2e"/>
<rect x="28" y="50" width="44" height="6" rx="2" fill="#1a1a2e"/>
<rect x="28" y="62" width="30" height="6" rx="2" fill="#1a1a2e"/>
<rect x="28" y="74" width="36" height="6" rx="2" fill="#1a1a2e"/>
</svg>'

# ── SCORM — stacked layers ────────────────────────────────────────────────────
icon scorm '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M50,12 L86,28 L86,38 L50,54 L14,38 L14,28 Z" fill="#FFED00"/>
<path d="M14,48 L50,64 L86,48 L86,58 L50,74 L14,58 Z" fill="#FFED00" opacity="0.7"/>
<path d="M14,68 L50,84 L86,68 L86,76 L50,90 L14,76 Z" fill="#FFED00" opacity="0.45"/>
</svg>'

# ── SUBSECTION — section divider ──────────────────────────────────────────────
icon subsection '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="10" y="16" width="80" height="14" rx="5" fill="#FFED00"/>
<rect x="10" y="40" width="56" height="10" rx="4" fill="#FFED00"/>
<rect x="10" y="56" width="56" height="10" rx="4" fill="#FFED00"/>
<rect x="10" y="72" width="56" height="10" rx="4" fill="#FFED00"/>
<rect x="70" y="36" width="20" height="50" rx="5" fill="#FFED00" opacity="0.5"/>
</svg>'

# ── SURVEY — bar chart ────────────────────────────────────────────────────────
icon survey '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<rect x="10" y="84" width="80" height="6" rx="2" fill="#FFED00"/>
<rect x="14" y="56" width="14" height="28" rx="3" fill="#FFED00"/>
<rect x="34" y="34" width="14" height="50" rx="3" fill="#FFED00"/>
<rect x="54" y="44" width="14" height="40" rx="3" fill="#FFED00"/>
<rect x="74" y="18" width="14" height="66" rx="3" fill="#FFED00" opacity="0.6"/>
</svg>'

# ── URL — chain link ──────────────────────────────────────────────────────────
icon url '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M44,60 Q28,76 20,68 Q10,58 24,44 L36,32 Q50,18 60,26 Q68,34 58,44 L50,52 Q46,48 52,42 L60,34 Q66,28 58,22 Q50,16 40,26 L28,38 Q18,48 24,56 Q30,64 40,54" stroke="#FFED00" stroke-width="10" stroke-linecap="round" fill="none"/>
<path d="M56,40 Q72,24 80,32 Q90,42 76,56 L64,68 Q50,82 40,74 Q32,66 42,56 L50,48 Q54,52 48,58 L40,66 Q34,72 42,78 Q50,84 60,74 L72,62 Q82,52 76,44 Q70,36 60,46" stroke="#FFED00" stroke-width="10" stroke-linecap="round" fill="none"/>
</svg>'

# ── WIKI — network nodes ──────────────────────────────────────────────────────
icon wiki '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<line x1="50" y1="22" x2="18" y2="72" stroke="#FFED00" stroke-width="6" stroke-linecap="round"/>
<line x1="50" y1="22" x2="82" y2="72" stroke="#FFED00" stroke-width="6" stroke-linecap="round"/>
<line x1="18" y1="72" x2="82" y2="72" stroke="#FFED00" stroke-width="6" stroke-linecap="round"/>
<circle cx="50" cy="22" r="12" fill="#FFED00"/>
<circle cx="18" cy="72" r="12" fill="#FFED00"/>
<circle cx="82" cy="72" r="12" fill="#FFED00"/>
<circle cx="50" cy="22" r="5" fill="#1a1a2e"/>
<circle cx="18" cy="72" r="5" fill="#1a1a2e"/>
<circle cx="82" cy="72" r="5" fill="#1a1a2e"/>
</svg>'

# ── WORKSHOP — gear ───────────────────────────────────────────────────────────
icon workshop '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
<rect width="100" height="100" rx="14" fill="#1a1a2e"/>
<path d="M44,10 L56,10 L59,22 Q65,24 70,28 L82,24 L90,34 L82,44 Q83,47 83,50 Q83,53 82,56 L90,66 L82,76 L70,72 Q65,76 59,78 L56,90 L44,90 L41,78 Q35,76 30,72 L18,76 L10,66 L18,56 Q17,53 17,50 Q17,47 18,44 L10,34 L18,24 L30,28 Q35,24 41,22 Z" fill="#FFED00"/>
<circle cx="50" cy="50" r="16" fill="#1a1a2e"/>
<circle cx="50" cy="50" r="8" fill="#FFED00"/>
</svg>'

echo ""
echo "Purging caches..."
docker exec moodle-web php /var/www/html/admin/cli/purge_caches.php
echo "All done!"
