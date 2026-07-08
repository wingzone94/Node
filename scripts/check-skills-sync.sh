#!/bin/bash
# SKILL.md の3ツール間（.claude / .gemini / .codex）ミラー同期を検査する。
# 正本は .claude/skills/。差分・欠落があれば一覧を出して exit 1。
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CANONICAL="$ROOT/.claude/skills"
MIRRORS=("$ROOT/.gemini/skills" "$ROOT/.codex/skills")

fail=0

if [ ! -d "$CANONICAL" ]; then
  echo "NG: 正本ディレクトリがありません: $CANONICAL"
  exit 1
fi

# 正本の各スキルがミラー側に同一内容で存在するか
for skill_dir in "$CANONICAL"/*/; do
  [ -d "$skill_dir" ] || continue
  name="$(basename "$skill_dir")"
  src="$skill_dir/SKILL.md"
  if [ ! -f "$src" ]; then
    echo "NG: $name — 正本に SKILL.md がありません"
    fail=1
    continue
  fi
  for mirror in "${MIRRORS[@]}"; do
    dst="$mirror/$name/SKILL.md"
    if [ ! -f "$dst" ]; then
      echo "NG: $name — ミラー欠落: ${dst#$ROOT/}"
      fail=1
    elif ! diff -q "$src" "$dst" >/dev/null; then
      echo "NG: $name — 内容が正本と異なります: ${dst#$ROOT/}"
      diff -u "$src" "$dst" | head -20
      fail=1
    fi
  done
done

# ミラー側にだけ存在する（正本にない）スキルの検出
for mirror in "${MIRRORS[@]}"; do
  [ -d "$mirror" ] || continue
  for skill_dir in "$mirror"/*/; do
    [ -d "$skill_dir" ] || continue
    name="$(basename "$skill_dir")"
    if [ ! -d "$CANONICAL/$name" ]; then
      echo "NG: $name — 正本 (.claude/skills) に存在しないスキルがミラーにあります: ${skill_dir#$ROOT/}"
      fail=1
    fi
  done
done

if [ "$fail" -eq 0 ]; then
  echo "OK: 全スキルが3ツール間で同期されています"
fi
exit "$fail"
