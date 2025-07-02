import os
import sys
from pathlib import Path
from runpy import run_path
from typing import List


def _get_testing_dirs(data_dir: str) -> List:
    """
    Gets directories within a directory that do not start with an underscore

    Args:
        data_dir: directory which holds directories

    Returns:
        list of paths inside directory
    """
    return [
        os.path.join(data_dir, o)
        for o in os.listdir(data_dir)
        if os.path.isdir(os.path.join(data_dir, o)) and not o.startswith("_") and not o == "legacy_v1"
    ]


def run_component(component_script, data_folder):
    """
    Runs a component script with a specified parameters
    """
    os.environ["KBC_DATADIR"] = data_folder
    if Path(data_folder).joinpath("exit_code").exists():
        expected_exitcode = int(open(Path(data_folder).joinpath("exit_code")).readline())
    else:
        expected_exitcode = 0
    try:
        run_path(component_script, run_name="__main__")
    except SystemExit as exeption:
        exitcode = exeption.code
    else:
        exitcode = 0

    if exitcode != expected_exitcode:
        raise AssertionError(f"Process failed with unexpected exit code {exitcode}, instead of {expected_exitcode}")
    # run_path(component_script, run_name='__main__')


test_dirs = _get_testing_dirs(Path(__file__).parent.absolute().joinpath("calls").as_posix())

component_script = Path(__file__).absolute().parent.parent.joinpath("src/component.py").as_posix()

print("\nRunning test calls\n")
os.environ["KBC_EXAMPLES_DIR"] = "/examples/"
for dir_path in test_dirs:
    print(f"\nRunning test {Path(dir_path).name}")
    sys.path.append(Path(component_script).parent.as_posix())
    run_component(component_script, dir_path)

print("\nAll tests finished successfully!")
