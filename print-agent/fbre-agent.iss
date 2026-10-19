; Instalador do Agente de Impressão (Inno Setup)
; Gera agenteprint-setup.exe a partir de dist\agenteprint.exe

[Setup]
AppName=Agente de Impressão
AppVersion=1.0
AppPublisher=Agente de Impressão
DefaultDirName={autopf}\Agente de Impressão
DefaultGroupName=Agente de Impressão
DisableProgramGroupPage=yes
DisableDirPage=yes
PrivilegesRequired=lowest
OutputDir=installer
OutputBaseFilename=agenteprint-setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern

[Languages]
Name: "pt"; MessagesFile: "compiler:Languages\BrazilianPortuguese.isl"

[Files]
Source: "dist\agenteprint.exe"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\Agente de Impressão"; Filename: "{app}\agenteprint.exe"
Name: "{group}\Configurar impressoras"; Filename: "{app}\agenteprint.exe"; Parameters: "--config"
Name: "{group}\Desinstalar"; Filename: "{uninstallexe}"
; inicia junto com o Windows
Name: "{userstartup}\Agente de Impressão"; Filename: "{app}\agenteprint.exe"

[Run]
; ao terminar a instalação, já abre a tela pra escolher impressoras e colar o token
Filename: "{app}\agenteprint.exe"; Parameters: "--config"; Description: "Configurar impressoras e token agora"; Flags: nowait postinstall skipifsilent
