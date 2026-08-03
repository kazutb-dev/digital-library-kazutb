# Figure Rendering Report

Date: 2026-05-14  
Scope: Render current final-improvements figure sources to real PNG assets.

## 1. Tools Used

- Node.js + `npx`
- Mermaid CLI package: `@mermaid-js/mermaid-cli` (invoked via `npx -y`)
- PowerShell for batch rendering and output normalization

## 2. Commands Run

Tool validation:

```powershell
npx -y @mermaid-js/mermaid-cli -h
```

Batch render from source markdown:

```powershell
$srcDir = 'qa/final-improvements/figure-sources'
$outDir = 'qa/final-improvements/figures'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

Get-ChildItem $srcDir -File -Filter 'figure-*.md' |
  Where-Object { $_.Name -ne 'figure-index.md' } |
  Sort-Object Name |
  ForEach-Object {
    npx -y @mermaid-js/mermaid-cli -i $_.FullName -o (Join-Path $outDir ($_.BaseName + '.png'))
  }
```

Filename normalization (Mermaid CLI emitted `-1` suffix for Markdown extraction):

```powershell
$outDir='qa/final-improvements/figures'
Get-ChildItem $outDir -File -Filter '*-1.png' | ForEach-Object {
  $newName = $_.Name -replace '-1\.png$','.png'
  Rename-Item -Path $_.FullName -NewName $newName -Force
}
```

## 3. Compatibility Fixes Needed

To improve Mermaid parser compatibility (Mermaid 8.8-style environments), source diagram blocks were adjusted before rendering:

- Replaced unsupported/fragile syntax patterns in:
    - `figure-03-risk-heatmap.md`
    - `figure-04-test-coverage-map.md`
    - `figure-05-quality-gates-dashboard.md`
    - `figure-06-risk-distribution-chart.md`
    - `figure-07-test-execution-timeline.md` (timeline syntax replaced with flowchart sequence)
    - `figure-08-coverage-vs-risk-matrix.md`
    - `figure-10-performance-dashboard.md`

No source-only fallback was required after these fixes.

## 4. Per-Figure Render Status

| Figure | Source File                                                                 | Output PNG                                                            | Rendering Method                            | Status  | Caveat                                          |
| ------ | --------------------------------------------------------------------------- | --------------------------------------------------------------------- | ------------------------------------------- | ------- | ----------------------------------------------- |
| 00     | `qa/final-improvements/figure-sources/figure-00-cicd-pipeline.md`           | `qa/final-improvements/figures/figure-00-cicd-pipeline.png`           | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | None                                            |
| 01     | `qa/final-improvements/figure-sources/figure-01-qa-pipeline.md`             | `qa/final-improvements/figures/figure-01-qa-pipeline.png`             | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | None                                            |
| 02     | `qa/final-improvements/figure-sources/figure-02-testing-architecture.md`    | `qa/final-improvements/figures/figure-02-testing-architecture.png`    | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | None                                            |
| 03     | `qa/final-improvements/figure-sources/figure-03-risk-heatmap.md`            | `qa/final-improvements/figures/figure-03-risk-heatmap.png`            | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |
| 04     | `qa/final-improvements/figure-sources/figure-04-test-coverage-map.md`       | `qa/final-improvements/figures/figure-04-test-coverage-map.png`       | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |
| 05     | `qa/final-improvements/figure-sources/figure-05-quality-gates-dashboard.md` | `qa/final-improvements/figures/figure-05-quality-gates-dashboard.png` | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |
| 06     | `qa/final-improvements/figure-sources/figure-06-risk-distribution-chart.md` | `qa/final-improvements/figures/figure-06-risk-distribution-chart.png` | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |
| 07     | `qa/final-improvements/figure-sources/figure-07-test-execution-timeline.md` | `qa/final-improvements/figures/figure-07-test-execution-timeline.png` | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Timeline block replaced with flowchart sequence |
| 08     | `qa/final-improvements/figure-sources/figure-08-coverage-vs-risk-matrix.md` | `qa/final-improvements/figures/figure-08-coverage-vs-risk-matrix.png` | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |
| 09     | `qa/final-improvements/figure-sources/figure-09-defect-detection-flow.md`   | `qa/final-improvements/figures/figure-09-defect-detection-flow.png`   | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | None                                            |
| 10     | `qa/final-improvements/figure-sources/figure-10-performance-dashboard.md`   | `qa/final-improvements/figures/figure-10-performance-dashboard.png`   | Mermaid CLI (`npx @mermaid-js/mermaid-cli`) | SUCCESS | Source syntax compatibility fix applied         |

## 5. Validation Results

- PNG files exist for all 11 figure sources.
- Filenames map cleanly source-to-output using matching base names.
- Figure index updated with explicit source and PNG output paths.
- Outputs are isolated in `qa/final-improvements/figures/` (no mixing with legacy phase directories).

## 6. Paper Insertion Readiness

Status: READY

- PNG assets are present and named consistently.
- Priority paper-facing figures requested in scope are rendered.
- Caveat-sensitive figures (conceptual or retained-baseline) remain labeled in index documentation.
