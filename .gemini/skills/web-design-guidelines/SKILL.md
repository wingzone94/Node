---
name: web-design-guidelines
description: Review UI code for Web Interface Guidelines compliance. Use when asked to "review my UI", "check accessibility", "audit design", "review UX", or "check my site against best practices".
---

# Web Interface Guidelines Review Skill

Review files for compliance with Web Interface Guidelines.

## Workflow

1. **Fetch Guidelines**: Fetch the latest guidelines from the source URL below using `web_fetch`.
   - URL: `https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md`
2. **Read Target Files**: Read the specified files or the entire codebase if a pattern is provided.
3. **Analyze Compliance**: Check the code against all rules in the fetched guidelines.
4. **Report Findings**: Output findings in the terse `file:line: message` format.

## Guidelines and Rules

- Always fetch fresh guidelines before each review to ensure up-to-date compliance checks.
- If no files are specified by the user, ask which files or directories should be reviewed.
- Focus on accessibility, UX consistency, and web best practices.
