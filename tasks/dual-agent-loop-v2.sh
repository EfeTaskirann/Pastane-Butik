#!/bin/bash
# ============================================================
# CLAUDE CODE DUAL-AGENT LOOP v2
# 
# Agent 1 (Developer):  Kodu yazar / düzeltir
# Agent 2 (Reviewer):   Kodu ÇALIŞTIRARAK test eder
# Agent 3 (Validator):  Reviewer APPROVED derse son kontrol
#
# Özellikler:
# - Reviewer kodu gerçekten çalıştırıp test etmek ZORUNDA
# - Minimum iterasyon zorunluluğu (erken approve koruması)
# - CLAUDE.md'ye öğrenilen dersler yazılır (hafıza sistemi)
# - Kalite skoru sistemi
# - Validator agent: Reviewer'ın approve'unu doğrular
# ============================================================

# -------------------- CONFIG --------------------
PROJECT_DIR="${1:-.}"
TASK_FILE="${2:-task.md}"
MAX_ITERATIONS="${3:-8}"
MIN_ITERATIONS="${4:-2}"              # Minimum bu kadar iterasyon ZORUNLU
LOG_DIR="$PROJECT_DIR/.agent-logs"
CLAUDE_MD="$PROJECT_DIR/CLAUDE.md"

# -------------------- SETUP --------------------
mkdir -p "$LOG_DIR"
ITERATION=0
STATUS="NEEDS_WORK"
VALIDATED="NO"

# Renk kodları
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   CLAUDE CODE DUAL-AGENT LOOP v2                 ║${NC}"
echo -e "${CYAN}║   Developer + Reviewer + Validator               ║${NC}"
echo -e "${CYAN}║   + CLAUDE.md Hafıza Sistemi                     ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# -------------------- GÖREV KONTROLÜ --------------------
if [ ! -f "$PROJECT_DIR/$TASK_FILE" ]; then
    echo -e "${RED}HATA: $PROJECT_DIR/$TASK_FILE bulunamadı!${NC}"
    echo ""
    echo "Kullanım:"
    echo "  ./dual-agent-loop.sh /proje/dizini task.md [maks_iter] [min_iter]"
    echo ""
    echo "Örnekler:"
    echo "  ./dual-agent-loop.sh . task.md          # maks 8, min 2 iterasyon"
    echo "  ./dual-agent-loop.sh . task.md 10 3     # maks 10, min 3 iterasyon"
    exit 1
fi

TASK_CONTENT=$(cat "$PROJECT_DIR/$TASK_FILE")

# -------------------- CLAUDE.MD BAŞLANGIÇ --------------------
# CLAUDE.md yoksa oluştur, varsa mevcut bilgiyi yükle
if [ ! -f "$CLAUDE_MD" ]; then
    cat > "$CLAUDE_MD" << 'CLAUDEMD_INIT'
# CLAUDE.md - Proje Hafızası

Bu dosya, geliştirme sürecinde öğrenilen dersleri ve kuralları içerir.
Her agent bu dosyayı OKUMAK ve UYMAK zorundadır.

## Proje Kuralları
- Placeholder veya TODO kodu yasaktır
- Her fonksiyon hata yönetimi içermelidir
- Kullanıcı girdileri her zaman sanitize edilmelidir

## Öğrenilen Dersler
<!-- Aşağıya otomatik olarak eklenir -->

## Tekrarlanan Hatalar
<!-- Birden fazla kez yapılan hatalar buraya taşınır -->

CLAUDEMD_INIT
    echo -e "${YELLOW}📝 CLAUDE.md oluşturuldu${NC}"
else
    echo -e "${GREEN}📝 Mevcut CLAUDE.md yüklendi${NC}"
fi

CLAUDE_MD_CONTENT=$(cat "$CLAUDE_MD")

echo -e "${YELLOW}📋 Görev:${NC} $TASK_FILE"
echo -e "${YELLOW}📁 Proje:${NC} $PROJECT_DIR"
echo -e "${YELLOW}🔄 İterasyon:${NC} min $MIN_ITERATIONS / maks $MAX_ITERATIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# -------------------- YARDIMCI FONKSİYONLAR --------------------
get_project_files() {
    find "$PROJECT_DIR" -type f \
        ! -path "*/.agent-logs/*" \
        ! -path "*/.git/*" \
        ! -path "*/node_modules/*" \
        ! -path "*/vendor/*" \
        ! -path "*/__pycache__/*" \
        ! -path "*/venv/*" \
        ! -name "*.lock" \
        ! -name "dual-agent-loop.sh" \
        ! -name "package-lock.json" \
        2>/dev/null | head -80
}

# -------------------- ANA DÖNGÜ --------------------
while [ $ITERATION -lt $MAX_ITERATIONS ]; do
    ITERATION=$((ITERATION + 1))
    CLAUDE_MD_CONTENT=$(cat "$CLAUDE_MD")

    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  İTERASYON $ITERATION / $MAX_ITERATIONS (minimum: $MIN_ITERATIONS)${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    # ==================== DEVELOPER AGENT ====================
    echo -e "${GREEN}🔨 [DEVELOPER] Kod yazılıyor...${NC}"

    if [ $ITERATION -eq 1 ]; then
        DEV_PROMPT="Sen bir developer agentsın.

ÖNCELİKLE CLAUDE.md DOSYASINI OKU VE İÇİNDEKİ KURALLARA UY:
$CLAUDE_MD_CONTENT

GÖREV:
$TASK_CONTENT

KURALLAR:
- CLAUDE.md'deki tüm kurallara ve öğrenilen derslere uy
- Dosyaları doğrudan oluştur/düzenle
- Tam ve çalışır kod yaz, placeholder/TODO yasak
- Her fonksiyonda hata yönetimi olsun
- İşin bitince hangi dosyaları oluşturduğunu listele"
    else
        PREV_REVIEW=$(cat "$LOG_DIR/review_$((ITERATION - 1)).md" 2>/dev/null)
        DEV_PROMPT="Sen bir developer agentsın. Reviewer sorunlar buldu, düzelt.

ÖNCELİKLE CLAUDE.md DOSYASINI OKU VE İÇİNDEKİ KURALLARA UY:
$CLAUDE_MD_CONTENT

ORİJİNAL GÖREV:
$TASK_CONTENT

REVIEWER FEEDBACK:
$PREV_REVIEW

KURALLAR:
- CLAUDE.md'deki öğrenilen derslere dikkat et, aynı hataları YAPMA
- Reviewer'ın belirttiği TÜM sorunları düzelt
- Placeholder/TODO yasak
- Düzelttiğin şeyleri listele"
    fi

    cd "$PROJECT_DIR"
    claude -p "$DEV_PROMPT" --output-format text 2>/dev/null | tee "$LOG_DIR/dev_${ITERATION}.md"
    echo ""

    # ==================== REVIEWER AGENT ====================
    echo -e "${YELLOW}🔍 [REVIEWER] Test ve inceleme yapılıyor...${NC}"

    FILE_LIST=$(get_project_files)

    # Reviewer'a gerçek test yapma zorunluluğu
    REVIEW_PROMPT="Sen bir senior QA engineer ve code reviewer agentsın.

ÖNCELİKLE CLAUDE.md DOSYASINI OKU:
$CLAUDE_MD_CONTENT

ORİJİNAL GÖREV:
$TASK_CONTENT

PROJEDEKİ DOSYALAR:
$FILE_LIST

ZORUNLU TEST ADIMLARI (hepsini yap, atlama):
1. TÜM proje dosyalarını oku
2. PHP dosyaları varsa: php -l ile syntax kontrolü yap
3. Python dosyaları varsa: python -m py_compile ile kontrol et
4. Görevdeki HER MADDEYİ tek tek kontrol et, karşılanmış mı?
5. Güvenlik kontrolü: SQL injection, XSS, CSRF, path traversal
6. Edge case kontrolü: boş input, çok uzun input, özel karakterler
7. Dosyalar arası bağımlılık kontrolü: import/require/include doğru mu?
8. CLAUDE.md'deki öğrenilen derslerdeki hatalar tekrarlanmış mı?

CEVAP FORMATI:

### Test Sonuçları
- Syntax kontrolü: [PASS/FAIL - detay]
- Fonksiyon testi: [PASS/FAIL - detay]
- Güvenlik testi: [PASS/FAIL - detay]
- Görev karşılama: [PASS/FAIL - detay]
- CLAUDE.md uyumu: [PASS/FAIL - detay]

### Bulunan Sorunlar
(varsa madde madde)

### Öğrenilen Dersler
(Bu iterasyonda keşfedilen yeni dersler - CLAUDE.md'ye eklenecek)

### Kalite Skoru
X/10 (detaylı açıklama)

### Karar
STATUS: APPROVED veya STATUS: NEEDS_WORK

ÖNEMLİ KURALLAR:
- SADECE tüm testler PASS ise APPROVED de
- Herhangi bir FAIL varsa NEEDS_WORK de
- Görevdeki herhangi bir madde eksikse NEEDS_WORK de
- Test YAPMADAN approve vermek YASAK
- 'İyi görünüyor' gibi tembel cevaplar YASAK, kanıtla"

    cd "$PROJECT_DIR"
    REVIEW_OUTPUT=$(claude -p "$REVIEW_PROMPT" --output-format text 2>/dev/null)
    echo "$REVIEW_OUTPUT" | tee "$LOG_DIR/review_${ITERATION}.md"
    echo ""

    # ==================== CLAUDE.MD GÜNCELLEME ====================
    # Reviewer'ın öğrenilen derslerini CLAUDE.md'ye ekle
    LESSONS=$(echo "$REVIEW_OUTPUT" | sed -n '/### Öğrenilen Dersler/,/### /p' | head -20 | grep -v "^###")

    if [ -n "$LESSONS" ] && [ "$LESSONS" != " " ]; then
        echo -e "${MAGENTA}📝 [HAFIZA] Yeni dersler CLAUDE.md'ye ekleniyor...${NC}"

        LESSON_PROMPT="Aşağıdaki reviewer derslerini CLAUDE.md dosyasına ekle.
CLAUDE.md'nin '## Öğrenilen Dersler' bölümünün altına yeni maddeleri ekle.
Her maddenin başına tarih ve iterasyon numarası koy.
Eğer aynı ders zaten varsa tekrarlama, ama '## Tekrarlanan Hatalar' bölümüne taşı.

Eklenecek dersler:
$LESSONS

Mevcut iterasyon: $ITERATION
Tarih: $(date '+%Y-%m-%d %H:%M')"

        cd "$PROJECT_DIR"
        claude -p "$LESSON_PROMPT" --output-format text 2>/dev/null > "$LOG_DIR/memory_${ITERATION}.md"
        CLAUDE_MD_CONTENT=$(cat "$CLAUDE_MD")
        echo -e "${MAGENTA}✅ CLAUDE.md güncellendi${NC}"
        echo ""
    fi

    # ==================== STATUS KONTROLÜ ====================
    FIRST_LINES=$(echo "$REVIEW_OUTPUT" | grep -i "STATUS:")
    
    if echo "$FIRST_LINES" | grep -qi "APPROVED"; then
        # ---- MİNİMUM İTERASYON KONTROLÜ ----
        if [ $ITERATION -lt $MIN_ITERATIONS ]; then
            echo -e "${YELLOW}⚠️  Reviewer APPROVED dedi ama minimum iterasyona ($MIN_ITERATIONS) ulaşılmadı.${NC}"
            echo -e "${YELLOW}    İterasyon $ITERATION < minimum $MIN_ITERATIONS → devam ediliyor.${NC}"
            echo -e "${YELLOW}    (Reviewer bir sonraki turda daha derinlemesine test edecek)${NC}"
            STATUS="NEEDS_WORK"

            # Reviewer'a daha derine inmesini söyle
            DEEPER_NOTE="NOT: Önceki reviewer APPROVED dedi ama minimum iterasyona ulaşılmadı. Daha derinlemesine test et: edge case'ler, performans, güvenlik açıkları, kod kalitesi." 
            echo "$DEEPER_NOTE" >> "$LOG_DIR/review_${ITERATION}.md"
            echo ""
            continue
        fi

        # ---- VALIDATOR AGENT (3. Agent) ----
        echo ""
        echo -e "${MAGENTA}🛡️  [VALIDATOR] Reviewer approve'u doğrulanıyor...${NC}"

        FILE_LIST=$(get_project_files)
        
        VALIDATE_PROMPT="Sen bir son kontrol (validation) agentsın. Reviewer bu kodu APPROVED olarak işaretledi.
Senin görevin: Bu approve DOĞRU MU kontrol etmek. Reviewer tembel approve yapmış olabilir.

ORİJİNAL GÖREV:
$TASK_CONTENT

REVIEWER'IN RAPORU:
$REVIEW_OUTPUT

PROJEDEKİ DOSYALAR:
$FILE_LIST

CLAUDE.md İÇERİĞİ:
$CLAUDE_MD_CONTENT

KONTROL LİSTESİ:
1. Görevdeki HER MADDEYİ tek tek say ve kontrol et
2. Reviewer gerçekten test yapmış mı? (syntax check, fonksiyon testi vs.)
3. Reviewer'ın test sonuçlarında FAIL var mı ama yine de APPROVED demiş mi?
4. Dosyaları oku ve reviewer'ın kaçırdığı bir şey var mı kontrol et
5. CLAUDE.md'deki kurallar ihlal edilmiş mi?
6. PHP/Python syntax kontrolü yap (php -l / python -m py_compile)

CEVAP FORMATI:

### Doğrulama Sonucu
VALIDATION: CONFIRMED veya VALIDATION: REJECTED

### Gerekçe
(Neden onayladın veya reddettin)

### Kaçırılan Sorunlar (varsa)
(Reviewer'ın fark etmediği şeyler)

ÖNEMLİ: Reviewer gerçek test yapmadan approve ettiyse, kesinlikle REJECTED de."

        cd "$PROJECT_DIR"
        VALIDATE_OUTPUT=$(claude -p "$VALIDATE_PROMPT" --output-format text 2>/dev/null)
        echo "$VALIDATE_OUTPUT" | tee "$LOG_DIR/validate_${ITERATION}.md"
        echo ""

        if echo "$VALIDATE_OUTPUT" | grep -qi "VALIDATION: CONFIRMED"; then
            VALIDATED="YES"
            STATUS="APPROVED"
            echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
            echo -e "${GREEN}║  ✅ ONAYLANDI! Reviewer + Validator onayladı       ║${NC}"
            echo -e "${GREEN}║  📊 Toplam iterasyon: $ITERATION                         ║${NC}"
            echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
            break
        else
            echo -e "${RED}🛡️  Validator REDDETTI! Reviewer'ın approve'u geçersiz.${NC}"
            echo -e "${RED}   Düzeltme için döngü devam ediyor...${NC}"
            STATUS="NEEDS_WORK"

            # Validator'ın bulduğu sorunları review log'una ekle
            echo "" >> "$LOG_DIR/review_${ITERATION}.md"
            echo "--- VALIDATOR EK NOTLAR ---" >> "$LOG_DIR/review_${ITERATION}.md"
            echo "$VALIDATE_OUTPUT" >> "$LOG_DIR/review_${ITERATION}.md"
        fi
    else
        STATUS="NEEDS_WORK"
        echo -e "${YELLOW}🔄 Reviewer düzeltme istedi → sonraki iterasyon${NC}"
    fi

    echo ""
done

# -------------------- FİNAL CLAUDE.MD ÖZET --------------------
echo ""
echo -e "${MAGENTA}📝 [HAFIZA] Final özet CLAUDE.md'ye ekleniyor...${NC}"

FINAL_PROMPT="CLAUDE.md dosyasının sonuna bu çalışmanın özetini ekle:

## Son Çalışma Özeti ($(date '+%Y-%m-%d %H:%M'))
- Görev: (kısaca)
- Toplam iterasyon: $ITERATION
- Sonuç: $STATUS
- Doğrulama: $VALIDATED

Tüm iterasyonlardaki öğrenilen derslerin bir özetini de '## Öğrenilen Dersler' bölümüne ekle (tekrar olmayanları)."

cd "$PROJECT_DIR"
claude -p "$FINAL_PROMPT" --output-format text 2>/dev/null > "$LOG_DIR/final_summary.md"

# -------------------- SONUÇ --------------------
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ "$STATUS" = "APPROVED" ] && [ "$VALIDATED" = "YES" ]; then
    echo -e "${GREEN}🎉 Proje başarıyla tamamlandı ve doğrulandı!${NC}"
elif [ "$STATUS" = "APPROVED" ]; then
    echo -e "${YELLOW}⚠️  Reviewer onayladı ama validator doğrulamadı.${NC}"
elif [ $ITERATION -ge $MAX_ITERATIONS ]; then
    echo -e "${RED}⚠️  Maksimum iterasyona ($MAX_ITERATIONS) ulaşıldı.${NC}"
    echo -e "${RED}    Manuel kontrol gerekebilir.${NC}"
fi
echo ""
echo -e "${CYAN}📂 Loglar:        $LOG_DIR/${NC}"
echo -e "${CYAN}📝 Proje hafızası: $CLAUDE_MD${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
