Set objShell = CreateObject("WScript.Shell")
objShell.Run "cmd.exe /c cd " & CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName) & " && php server.php", 0, False
