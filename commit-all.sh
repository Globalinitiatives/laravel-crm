#!/bin/bash
cd /home/amilcar/leaddesk/krayin

# Clean up temporary files from investigation
rm -f build-admin.sh check-manifest.py find-blade-logo.py find-krayin-references.py \
  fix-arabic-and-cleanup.py fix-env-db.py rebuild-and-install.sh rebuild-final.sh \
  Dockerfile.bak.* merge-admin-deps.js 2>/dev/null

# Ensure Vite build output dirs are gitignored (not committed)
grep -qxF '/public/build' .gitignore 2>/dev/null || echo '/public/build' >> .gitignore
grep -qxF '/public/installer/build' .gitignore 2>/dev/null || echo '/public/installer/build' >> .gitignore
grep -qxF '/public/admin/build' .gitignore 2>/dev/null || echo '/public/admin/build' >> .gitignore
grep -qxF '/public/webform/build' .gitignore 2>/dev/null || echo '/public/webform/build' >> .gitignore

# Remove build artifacts from tracked files (if any were accidentally committed)
git rm -r --cached public/build public/installer/build public/admin/build public/webform/build 2>/dev/null || true

# Stage ALL changes (including package.json with merged admin deps)
git add -A

if git diff --cached --quiet; then
    echo "No changes to commit"
    exit 0
fi

git commit -m "feat: UpDesk CRM customization - branding, deployment pipeline, Vite build fix"
git push origin main
