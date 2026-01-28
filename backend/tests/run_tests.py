"""
Script to run all tests and generate a test report.
"""
import sys
import subprocess
from pathlib import Path
from datetime import datetime

def run_tests():
    """Run pytest and generate report."""
    print("=" * 60)
    print("Running Task Management Module Tests")
    print("=" * 60)
    print()
    
    # Run pytest with coverage
    result = subprocess.run(
        [
            sys.executable,
            "-m",
            "pytest",
            "tests/",
            "-v",
            "--tb=short",
            "--cov=app.services",
            "--cov=app.api.routes.tasks",
            "--cov-report=term-missing",
            "--cov-report=html:htmlcov",
        ],
        capture_output=True,
        text=True,
    )
    
    print(result.stdout)
    if result.stderr:
        print("STDERR:", result.stderr)
    
    # Generate markdown report
    report_lines = [
        "# Task Management Module - Test Report",
        "",
        f"**Generated:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
        "",
        "## Test Execution Summary",
        "",
        f"**Exit Code:** {result.returncode}",
        "",
        "## Test Results",
        "",
        "```",
        result.stdout,
        "```",
        "",
        "## Coverage Report",
        "",
        "See `htmlcov/index.html` for detailed coverage report.",
        "",
    ]
    
    report_path = Path("TEST_REPORT.md")
    report_path.write_text("\n".join(report_lines))
    
    print()
    print("=" * 60)
    print(f"Test report saved to: {report_path.absolute()}")
    print("=" * 60)
    
    return result.returncode

if __name__ == "__main__":
    sys.exit(run_tests())




















