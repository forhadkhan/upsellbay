#!/usr/bin/env bash
set -e

ZIP="upsellbay.zip"
TMP="/tmp/upsellbay-release-verify"

echo "Verifying release ZIP: $ZIP"

if [ ! -f "$ZIP" ]; then
	echo "❌ ZIP not found: $ZIP"
	exit 1
fi

rm -rf "$TMP" && mkdir -p "$TMP"
unzip -q "$ZIP" -d "$TMP"

PLUGIN="$TMP/upsellbay"

# Required files — representative sample from each ship directory.
REQUIRED=(
	"upsellbay.php"
	"uninstall.php"
	"app/Core/Plugin.php"
	"app/Core/Constants.php"
	"app/Admin/Offers/OfferListTable.php"
	"app/Api/Routes/LicenseRoute.php"
	"app/Integrations/Licensing/LicenseClient.php"
	"app/Data/OfferRepository.php"
	"app/Data/StatsRepository.php"
	"app/Domain/Analytics/AnalyticsRecorder.php"
	"app/Domain/Offers/OfferValidator.php"
	"app/Domain/Storefront/ClassicCheckoutBump.php"
	"app/Utils/Logger.php"
	"assets/admin/js/upsellbay-admin.js"
	"assets/frontend/classic-checkout.js"
	"templates/admin/wizard.php"
	"templates/storefront/checkout-bump.php"
	"vendor/autoload.php"
	"languages/upsellbay.pot"
)

for f in "${REQUIRED[@]}"; do
	if [ ! -e "$PLUGIN/$f" ]; then
		echo "❌ MISSING required file: $f"
		exit 1
	fi
	echo "✅ Found: $f"
done

# Forbidden files/dirs.
FORBIDDEN=(
	"src"
	"node_modules"
	"AGENTS.md"
	"GEMINI.md"
	".meta"
	"graphify-out"
	"phpcs.xml"
	"phpstan.neon"
	"phpstan-bootstrap.php"
	"webpack.config.js"
	"package.json"
	"bun.lock"
	"scripts"
	".git"
	".env"
	".history"
	".agents"
	".codex"
	".kilo"
	".letta"
	".opencode"
	".githooks"
	".github"
	".graphifyignore"
	"tests"
	"docs"
)

for f in "${FORBIDDEN[@]}"; do
	if [ -e "$PLUGIN/$f" ]; then
		echo "❌ FORBIDDEN file/dir present: $f"
		exit 1
	fi
	echo "✅ Absent (correct): $f"
done

# Check plugin header version matches Constants::VERSION.
HEADER_VERSION=$(grep "Version:" "$PLUGIN/upsellbay.php" | head -1 | awk '{print $NF}' | tr -d '[:space:]')
CONST_VERSION=$(grep "const VERSION" "$PLUGIN/app/Core/Constants.php" | grep -o "'[0-9.]*'" | tr -d "'")

if [ "$HEADER_VERSION" != "$CONST_VERSION" ]; then
	echo "❌ Version mismatch: header=$HEADER_VERSION constant=$CONST_VERSION"
	exit 1
fi
echo "✅ Version consistent: $HEADER_VERSION"

rm -rf "$TMP"
echo ""
echo "✅ Release verification passed."
