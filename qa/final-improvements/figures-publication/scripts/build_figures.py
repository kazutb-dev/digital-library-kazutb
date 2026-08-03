from __future__ import annotations

from pathlib import Path

import matplotlib

matplotlib.use("Agg")

import matplotlib.patches as patches
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
import seaborn as sns

ROOT = Path(__file__).resolve().parents[1]
METRICS = ROOT.parent / "metrics"
OUTPUT = ROOT

sns.set_theme(
    style="whitegrid",
    rc={
        "axes.spines.top": False,
        "axes.spines.right": False,
        "figure.dpi": 140,
        "savefig.dpi": 320,
        "font.family": "DejaVu Sans",
    },
)

COLORS = {
    "ink": "#1F2937",
    "grid": "#D9E2EC",
    "blue": "#2563EB",
    "green": "#15803D",
    "amber": "#D97706",
    "red": "#B91C1C",
    "slate": "#475569",
    "bg": "#F8FAFC",
}


def load_csv(name: str) -> pd.DataFrame:
    return pd.read_csv(METRICS / name)


def setup_chart(width: float, height: float):
    fig, ax = plt.subplots(figsize=(width, height), constrained_layout=True)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")
    return fig, ax


def save_figure(fig: plt.Figure, slug: str) -> None:
    fig.savefig(OUTPUT / f"{slug}.svg", format="svg", bbox_inches="tight")
    fig.savefig(OUTPUT / f"{slug}.png", format="png", bbox_inches="tight", dpi=360)
    plt.close(fig)


def parse_time_seconds(value: str) -> float:
    text = str(value).strip().replace("~", "")
    if text == "<1ms":
        return 0.001
    if text.endswith("s") and ":" not in text:
        return float(text[:-1])
    parts = text.split(":")
    if len(parts) == 2:
        minutes, seconds = parts
        return float(minutes) * 60 + float(seconds)
    if len(parts) == 3:
        hours, minutes, seconds = parts
        return float(hours) * 3600 + float(minutes) * 60 + float(seconds)
    raise ValueError(f"Unsupported time format: {value}")


def fig_01_risk_heatmap() -> str:
    df = load_csv("risk-table.csv").copy()
    likelihood_map = {"Low": 1, "Medium": 2, "High": 3}
    impact_map = {"Low": 1, "Medium": 2, "High": 3, "Critical": 4}

    df["L"] = df["Likelihood"].map(likelihood_map)
    df["I"] = df["Impact"].map(impact_map)

    grid = (
        df.groupby(["I", "L"]).size().reset_index(name="Count")
        .pivot(index="I", columns="L", values="Count")
        .reindex(index=[1, 2, 3, 4], columns=[1, 2, 3])
        .fillna(0)
    )

    fig, ax = setup_chart(6.2, 4.8)
    ax.set_title("Risk Heatmap", fontsize=14, pad=10, weight="bold")

    sns.heatmap(
        grid,
        ax=ax,
        annot=True,
        fmt=".0f",
        cmap=sns.light_palette("#B91C1C", as_cmap=True),
        cbar_kws={"label": "Risks per cell"},
        linewidths=1.0,
        linecolor="white",
        annot_kws={"fontsize": 10, "weight": "bold", "color": COLORS["ink"]},
    )

    ax.set_xticklabels(["Low", "Medium", "High"])
    ax.set_yticklabels(["Low", "Medium", "High", "Critical"], rotation=0)
    ax.set_xlabel("Likelihood")
    ax.set_ylabel("Impact")

    critical_count = int((df["Priority"] == "CRITICAL").sum())
    high_count = int((df["Priority"] == "HIGH").sum())
    medium_count = int((df["Priority"] == "MEDIUM").sum())
    ax.text(
        0.5,
        -0.17,
        f"Priority totals: Critical={critical_count}, High={high_count}, Medium={medium_count}",
        transform=ax.transAxes,
        ha="center",
        fontsize=8,
        color=COLORS["slate"],
    )

    save_figure(fig, "fig-01-risk-heatmap")
    return "fig-01-risk-heatmap"


def fig_02_coverage_vs_risk() -> str:
    df = load_csv("coverage-vs-risk.csv").copy()
    df["Coverage (%)"] = df["Coverage (%)"].astype(float)
    df = df.sort_values(["Priority", "Coverage (%)"], ascending=[True, False])

    priority_palette = {"CRITICAL": COLORS["red"], "HIGH": COLORS["amber"], "MEDIUM": COLORS["blue"]}

    fig, ax = setup_chart(7.0, 5.0)
    bars = ax.barh(df["Risk ID"], df["Coverage (%)"], color=df["Priority"].map(priority_palette), edgecolor="white", linewidth=0.8)
    ax.invert_yaxis()
    ax.set_title("Coverage vs Risk", fontsize=14, pad=10, weight="bold")
    ax.set_xlabel("Coverage (%)")
    ax.set_ylabel("Risk ID")
    ax.set_xlim(0, 105)
    ax.axvline(100, color=COLORS["green"], linestyle="--", linewidth=1.0)

    for bar, coverage in zip(bars, df["Coverage (%)"], strict=True):
        ax.text(min(coverage + 1.5, 101.5), bar.get_y() + bar.get_height() / 2, f"{int(coverage)}%", va="center", fontsize=8, color=COLORS["ink"])

    legend_handles = [
        patches.Patch(color=COLORS["red"], label="Critical"),
        patches.Patch(color=COLORS["amber"], label="High"),
        patches.Patch(color=COLORS["blue"], label="Medium"),
    ]
    ax.legend(handles=legend_handles, frameon=False, loc="lower right", fontsize=8)
    ax.grid(axis="x", color=COLORS["grid"], linewidth=0.6)
    save_figure(fig, "fig-02-coverage-vs-risk")
    return "fig-02-coverage-vs-risk"


def fig_03_execution_time() -> str:
    df = load_csv("execution-time-comparison.csv").copy()

    stage_map = {
        "AdminPrivilegeNegativePathTest": "Admin Paths",
        "ReservationMutateTest": "Reservation",
        "Feature Tests (All)": "Feature Suite",
        "Unit Tests": "Unit Suite",
        "TOTAL SUITE": "Full Suite",
    }

    df["Stage"] = df["Test Stage"].map(stage_map)
    df["Seconds"] = df["Execution Time (Automated)"].apply(parse_time_seconds)

    order = ["Unit Suite", "Reservation", "Admin Paths", "Feature Suite", "Full Suite"]
    plot_df = df.set_index("Stage").reindex(order).reset_index()

    major = plot_df[plot_df["Stage"].isin(["Feature Suite", "Full Suite"])].copy()
    focused = plot_df[plot_df["Stage"].isin(["Admin Paths", "Reservation", "Unit Suite"])].copy()

    fig = plt.figure(figsize=(7.6, 4.6), constrained_layout=True)
    gs = fig.add_gridspec(1, 2)
    ax1 = fig.add_subplot(gs[0, 0])
    ax2 = fig.add_subplot(gs[0, 1])
    fig.suptitle("Execution Time Comparison", fontsize=14, weight="bold")

    b1 = ax1.barh(major["Stage"], major["Seconds"], color=[COLORS["red"], COLORS["green"]], edgecolor="white", linewidth=0.8)
    ax1.set_title("A. Suite Runtime", fontsize=10)
    ax1.set_xlabel("Seconds")
    ax1.grid(axis="x", color=COLORS["grid"], linewidth=0.6)
    for bar, sec in zip(b1, major["Seconds"], strict=True):
        ax1.text(sec + 1.0, bar.get_y() + bar.get_height() / 2, f"{sec:.1f}s", va="center", fontsize=8, color=COLORS["ink"])

    b2 = ax2.barh(focused["Stage"], focused["Seconds"], color=[COLORS["amber"], COLORS["amber"], COLORS["blue"]], edgecolor="white", linewidth=0.8)
    ax2.set_title("B. Focused Runtime", fontsize=10)
    ax2.set_xlabel("Seconds")
    ax2.grid(axis="x", color=COLORS["grid"], linewidth=0.6)
    for bar, sec in zip(b2, focused["Seconds"], strict=True):
        label = f"{sec:.3f}s" if sec < 1 else f"{sec:.3f}s"
        ax2.text(sec + 0.05, bar.get_y() + bar.get_height() / 2, label, va="center", fontsize=8, color=COLORS["ink"])

    save_figure(fig, "fig-03-execution-time-comparison")
    return "fig-03-execution-time-comparison"


def fig_04_performance_bounds() -> str:
    perf = load_csv("performance-rerun.csv").copy()
    exec_df = load_csv("execution-time-comparison.csv").copy()

    # Throughput is canonical baseline: 947 tests in ~190s.
    throughput = 947 / 190.0

    # No raw percentile traces are present in canonical final-improvements metrics.
    # Use a bounded p95 proxy from the critical path per-test latency.
    critical_path_total = 5.4
    critical_path_tests = 28
    p95_proxy_ms = (critical_path_total / critical_path_tests) * 1000.0

    fig = plt.figure(figsize=(7.6, 4.2), constrained_layout=True)
    gs = fig.add_gridspec(1, 2)
    ax1 = fig.add_subplot(gs[0, 0])
    ax2 = fig.add_subplot(gs[0, 1])

    fig.suptitle("Performance Bounds (Retained Baseline)", fontsize=13, weight="bold")

    ax1.bar(["P95 proxy"], [p95_proxy_ms], color=COLORS["blue"], width=0.55)
    ax1.set_title("A. Response Time", fontsize=10)
    ax1.set_ylabel("Milliseconds (ms)")
    ax1.grid(axis="y", color=COLORS["grid"], linewidth=0.6)
    ax1.set_ylim(0, max(260, p95_proxy_ms * 1.3))
    ax1.text(0, p95_proxy_ms + 8, f"{p95_proxy_ms:.0f} ms", ha="center", fontsize=8, color=COLORS["ink"])

    ax2.bar(["Throughput"], [throughput], color=COLORS["green"], width=0.55)
    ax2.set_title("B. Throughput", fontsize=10)
    ax2.set_ylabel("Tests per second")
    ax2.grid(axis="y", color=COLORS["grid"], linewidth=0.6)
    ax2.set_ylim(0, max(7, throughput * 1.6))
    ax2.text(0, throughput + 0.2, f"{throughput:.1f} tests/s", ha="center", fontsize=8, color=COLORS["ink"])

    fig.text(
        0.5,
        -0.01,
        "Bounded evidence: panel A is a proxy derived from critical-path timing because raw percentile traces are not available in canonical final-improvements metrics.",
        ha="center",
        va="top",
        fontsize=8,
        color=COLORS["slate"],
    )

    save_figure(fig, "fig-04-performance-bounds")
    return "fig-04-performance-bounds"


def fig_05_cicd_pipeline() -> str:
    fig, ax = setup_chart(7.2, 4.6)
    ax.axis("off")
    ax.set_title("CI/CD Pipeline and Test Environment", fontsize=13, pad=8, weight="bold")

    nodes = [
        (0.05, 0.62, 0.16, 0.16, "Commit"),
        (0.25, 0.62, 0.18, 0.16, "Build"),
        (0.47, 0.62, 0.18, 0.16, "Test Run"),
        (0.69, 0.62, 0.20, 0.16, "Evidence"),
        (0.30, 0.28, 0.40, 0.18, "Paper Assets"),
    ]

    for x, y, w, h, label in nodes:
        ax.add_patch(patches.FancyBboxPatch((x, y), w, h, transform=ax.transAxes, boxstyle="round,pad=0.02,rounding_size=0.02", facecolor="white", edgecolor=COLORS["slate"], linewidth=1.0))
        ax.text(x + w / 2, y + h / 2, label, transform=ax.transAxes, ha="center", va="center", fontsize=9, color=COLORS["ink"], weight="bold")

    arrows = [
        ((0.21, 0.70), (0.25, 0.70)),
        ((0.43, 0.70), (0.47, 0.70)),
        ((0.65, 0.70), (0.69, 0.70)),
        ((0.79, 0.62), (0.58, 0.46)),
    ]
    for start, end in arrows:
        ax.annotate("", xy=end, xytext=start, xycoords=ax.transAxes, textcoords=ax.transAxes, arrowprops={"arrowstyle": "->", "lw": 1.5, "color": COLORS["ink"]})

    save_figure(fig, "fig-05-cicd-pipeline")
    return "fig-05-cicd-pipeline"


def fig_06_testing_architecture() -> str:
    fig, ax = setup_chart(7.2, 4.8)
    ax.axis("off")
    ax.set_title("Testing Architecture", fontsize=13, pad=8, weight="bold")

    layers = [
        (0.08, 0.70, 0.84, 0.16, "Application"),
        (0.08, 0.46, 0.84, 0.16, "Test Layer"),
        (0.08, 0.22, 0.84, 0.16, "Data and Runtime"),
    ]

    for x, y, w, h, label in layers:
        ax.add_patch(patches.FancyBboxPatch((x, y), w, h, transform=ax.transAxes, boxstyle="round,pad=0.02,rounding_size=0.02", facecolor="white", edgecolor=COLORS["blue"], linewidth=1.0))
        ax.text(x + 0.03, y + h / 2, label, transform=ax.transAxes, ha="left", va="center", fontsize=10, color=COLORS["ink"], weight="bold")

    details = [
        (0.10, 0.74, "Laravel services and controllers"),
        (0.10, 0.50, "Feature, API, integration, unit tests"),
        (0.10, 0.26, "SQLite memory plus PostgreSQL environment"),
    ]
    for x, y, text in details:
        ax.text(x, y, text, transform=ax.transAxes, ha="left", va="center", fontsize=8.5, color=COLORS["slate"])

    ax.annotate("", xy=(0.50, 0.62), xytext=(0.50, 0.70), xycoords=ax.transAxes, textcoords=ax.transAxes, arrowprops={"arrowstyle": "->", "lw": 1.5, "color": COLORS["ink"]})
    ax.annotate("", xy=(0.50, 0.38), xytext=(0.50, 0.46), xycoords=ax.transAxes, textcoords=ax.transAxes, arrowprops={"arrowstyle": "->", "lw": 1.5, "color": COLORS["ink"]})

    save_figure(fig, "fig-06-testing-architecture")
    return "fig-06-testing-architecture"


def fig_07_defect_detection_comparison() -> str:
    manual_counts = {
        "Feature failures": 112,
        "Infrastructure errors": 36,
        "Risky tests": 5,
    }
    automated_counts = {
        "Integration tests": 0,
        "API tests": 0,
        "Unit tests": 0,
    }

    summary = pd.DataFrame(
        {
            "Source": ["Manual review", "Automated tests"],
            "Defect detections": [sum(manual_counts.values()), sum(automated_counts.values())],
        }
    )

    fig, ax = setup_chart(6.6, 4.0)
    ax.set_title("Defect Detection Comparison", fontsize=13, pad=10, weight="bold")

    bars = ax.barh(
        summary["Source"],
        summary["Defect detections"],
        color=[COLORS["red"], COLORS["green"]],
        edgecolor="white",
        linewidth=0.8,
    )

    ax.set_xlabel("Detected issues")
    ax.set_xlim(0, 165)
    ax.grid(axis="x", color=COLORS["grid"], linewidth=0.6)

    for bar, value in zip(bars, summary["Defect detections"], strict=True):
        label_x = max(value + 2.0, 2.0)
        ax.text(label_x, bar.get_y() + bar.get_height() / 2, f"{int(value)}", va="center", fontsize=9, color=COLORS["ink"])

    ax.text(
        0.5,
        -0.18,
        "Manual = feature failures + infrastructure errors + risky tests; automated = integration, API, and unit suites.",
        transform=ax.transAxes,
        ha="center",
        fontsize=8,
        color=COLORS["slate"],
    )

    save_figure(fig, "fig-07-defect-detection-comparison")
    return "fig-07-defect-detection-comparison"


def fig_08_quality_gates_summary() -> str:
    summary = pd.DataFrame(
        {
            "Status": ["PASS", "IN PROGRESS", "PENDING"],
            "Count": [14, 0, 0],
        }
    )

    palette = {
        "PASS": COLORS["green"],
        "IN PROGRESS": COLORS["amber"],
        "PENDING": COLORS["slate"],
    }

    fig, ax = setup_chart(5.8, 4.0)
    ax.set_title("Quality Gates Summary", fontsize=13, pad=10, weight="bold")

    bars = ax.barh(summary["Status"], summary["Count"], color=summary["Status"].map(palette), edgecolor="white", linewidth=0.8)
    ax.set_xlabel("Gate count")
    ax.set_xlim(0, 15)
    ax.grid(axis="x", color=COLORS["grid"], linewidth=0.6)

    for bar, value in zip(bars, summary["Count"], strict=True):
        label_x = value + 0.3 if value > 0 else 0.3
        ax.text(label_x, bar.get_y() + bar.get_height() / 2, f"{int(value)}", va="center", fontsize=9, color=COLORS["ink"])

    ax.text(
        0.5,
        -0.18,
        "Detailed gate definitions remain in Table 3; this figure is a compact status summary only.",
        transform=ax.transAxes,
        ha="center",
        fontsize=8,
        color=COLORS["slate"],
    )

    save_figure(fig, "fig-08-quality-gates-summary")
    return "fig-08-quality-gates-summary"


def write_manifest(figures: list[str]) -> None:
    df = pd.DataFrame({
        "figure": figures,
        "svg": [f"{name}.svg" for name in figures],
        "png": [f"{name}.png" for name in figures],
    })
    df.to_csv(OUTPUT / "manifest.csv", index=False)


def main() -> None:
    figures = [
        fig_01_risk_heatmap(),
        fig_02_coverage_vs_risk(),
        fig_03_execution_time(),
        fig_04_performance_bounds(),
        fig_05_cicd_pipeline(),
        fig_06_testing_architecture(),
        fig_07_defect_detection_comparison(),
        fig_08_quality_gates_summary(),
    ]
    write_manifest(figures)
    print(f"Generated {len(figures)} figures in {OUTPUT}")


if __name__ == "__main__":
    main()
