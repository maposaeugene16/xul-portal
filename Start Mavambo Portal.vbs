Set shell = CreateObject("WScript.Shell")
Set files = CreateObject("Scripting.FileSystemObject")
folder = files.GetParentFolderName(WScript.ScriptFullName)
script = folder & "\start-portal.ps1"
command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File """ & script & """"
shell.Run command, 0, False
