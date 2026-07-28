# Release Process

## Version scheme

`vMAJOR.MINOR.PATCH` — follows [Semantic Versioning](https://semver.org)

| Change | Bump |
|--------|------|
| Breaking API / schema change | MAJOR |
| New feature, backward-compatible | MINOR |
| Bug fix | PATCH |

## Branch strategy

```
main          ← production releases only (tags vX.Y.Z trigger deploy.yml)
  ↑ merged PR
release/vX.Y.Z
  ↑ branched from
develop       ← all features, fixes integrated here
  ↑ merged PRs
feature/*  fix/*  hotfix/*
```

## Release checklist

1. **Integration complete on `develop`**
   - [ ] All feature PRs merged to `develop`
   - [ ] `composer qa:ci` passes locally
   - [ ] `npm run build` succeeds
   - [ ] PHPUnit and Playwright tests pass

2. **Create release branch**
   ```bash
   git checkout develop && git pull
   git checkout -b release/v1.2.0
   ```

3. **Prepare release**
   - [ ] Update `CHANGELOG.md` (if exists)
   - [ ] Verify `.env.prod.example` is up to date
   - [ ] Check all new migrations are reversible

4. **Tag and push**
   ```bash
   git tag v1.2.0
   git push origin v1.2.0      # triggers deploy.yml → production
   ```

5. **Merge back**
   ```bash
   # merge release branch into main
   git checkout main && git merge release/v1.2.0
   git push origin main
   # merge release branch back into develop
   git checkout develop && git merge release/v1.2.0
   git push origin develop
   ```

6. **Verify production**
   - [ ] `GET /api/v1/catalog-db?limit=1` returns 200
   - [ ] Login flow works
   - [ ] Migration completed (check deploy logs)

## Hotfix process

For critical production bugs:

```bash
git checkout main
git checkout -b hotfix/v1.2.1
# fix the bug
git tag v1.2.1
git push origin v1.2.1          # triggers deploy
git checkout develop && git merge hotfix/v1.2.1
```

## CI/CD pipelines

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| `ci.yml` | push/PR to `main` | Quality gate: tests, lint, build |
| `deploy.yml` | push tag `v*` | Build Docker image, deploy to server |
| `release-package.yml` | push tag `v*` or manual | Build source archive artifact |

## Required GitHub Secrets for deploy

| Secret | Description |
|--------|-------------|
| `SERVER_HOST` | Production server IP/hostname |
| `SERVER_USER` | SSH user |
| `SERVER_SSH_KEY` | Private key (ED25519 recommended) |
| `SERVER_PATH` | Deployment path, e.g. `/opt/library` |

Set `DEPLOY_ENABLED=true` in GitHub repository variables to activate SSH deploy step in `deploy.yml`.
