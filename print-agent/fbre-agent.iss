; Instalador do FBRE Print Agent (Inno Setup)
; Gera FBREPrintAgentSetup.exe a partir de dist\FBREPrintAgent.exe

[Setup]
AppName=FBRE Print Agent
AppVersion=1.0
AppPublisher=GERATEC
DefaultDirName={autopf}\FBRE Print Agent
DefaultGroupName=FBRE Print Agent
DisableProgramGroupPage=yes
DisableDirPage=yes
PrivilegesRequired=lowest
OutputDir=installer
OutputBaseFilename=FBREPrintAgentSetup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern

[Languages]
Name: "pt"; MessagesFile: "compiler:Languages\BrazilianPortuguese.isl"

[Files]
Source: "dist\FBREPrintAgent.exe"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\FBRE Print Agent"; Filename: "{app}\FBREPrintAgent.exe"
Name: "{group}\Configurar impressoras"; Filename: "{app}\FBREPrintAgent.exe"; Parameters: "--config"
Name: "{group}\Desinstalar"; Filename: "{uninstallexe}"
; inicia junto com o Windows
Name: "{userstartup}\FBRE Print Agent"; Filename: "{app}\FBREPrintAgent.exe"

[Run]
; ao terminar a instalação, já abre a tela pra escolher impressoras e colar o token
Filename: "{app}\FBREPrintAgent.exe"; Parameters: "--config"; Description: "Configurar impressoras e token agora"; Flags: nowait postinstall skipifsilent
