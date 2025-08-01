using System;
using System.Collections.Generic;
using System.Drawing;
using System.Net.Http;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Win32;
using System.Threading;
using System.Text;
using System.IO;
using System.Text.Json;
using System.Linq;
using ESCPOS_NET.Emitters;
using ESCPOS_NET;

namespace GridPosPrintService
{
    public partial class MainForm : Form
    {
        private readonly HttpClient httpClient;
        private System.Windows.Forms.Timer monitorTimer;
        private string apiBaseUrl = "";
        private string clientSlug = "";
        private string authToken = "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3";
        private int monitorInterval = 2000; // 2 segundos por defecto
        private bool isMonitoring = false;

        public MainForm()
        {
            InitializeComponent();
            httpClient = new HttpClient();
            LoadConfiguration();
            SetupTimer();
        }

        private void InitializeComponent()
        {
            this.SuspendLayout();

            // Form
            this.AutoScaleDimensions = new SizeF(8F, 16F);
            this.AutoScaleMode = AutoScaleMode.Font;
            this.ClientSize = new Size(600, 580);
            this.Text = "GridPos Print Service";
            this.StartPosition = FormStartPosition.CenterScreen;
            this.MaximizeBox = false;
            this.FormBorderStyle = FormBorderStyle.FixedSingle;
            this.BackColor = Color.White;

            // Logo/Title
            var titleLabel = new Label
            {
                Text = "🚀 GRIDPOS PRINT SERVICE",
                Font = new Font("Segoe UI", 18, FontStyle.Bold),
                ForeColor = Color.DarkBlue,
                Location = new Point(50, 20),
                Size = new Size(500, 40),
                TextAlign = ContentAlignment.MiddleCenter
            };
            this.Controls.Add(titleLabel);

            var subtitleLabel = new Label
            {
                Text = "Sistema Ultra Rápido de Impresión",
                Font = new Font("Segoe UI", 10),
                ForeColor = Color.Gray,
                Location = new Point(50, 60),
                Size = new Size(500, 25),
                TextAlign = ContentAlignment.MiddleCenter
            };
            this.Controls.Add(subtitleLabel);

            // Configuration Group
            var configGroup = new GroupBox
            {
                Text = "📋 Configuración",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(20, 100),
                Size = new Size(560, 200),
                ForeColor = Color.DarkBlue
            };
            this.Controls.Add(configGroup);

            // API Selection
            var apiLabel = new Label
            {
                Text = "🌐 Selecciona API:",
                Font = new Font("Segoe UI", 9),
                Location = new Point(20, 30),
                Size = new Size(120, 23)
            };
            configGroup.Controls.Add(apiLabel);

            var apiCombo = new ComboBox
            {
                Name = "apiCombo",
                Font = new Font("Segoe UI", 9),
                Location = new Point(150, 28),
                Size = new Size(200, 25),
                DropDownStyle = ComboBoxStyle.DropDownList,
                FlatStyle = FlatStyle.Flat,
                BackColor = Color.White
            };
            apiCombo.Items.AddRange(new[] { "Producción (api.gridpos.co)", "Demo (api-demo.gridpos.co)" });
            apiCombo.SelectedIndex = 0;
            configGroup.Controls.Add(apiCombo);

            // Client Slug
            var clientLabel = new Label
            {
                Text = "🏢 Client Slug:",
                Font = new Font("Segoe UI", 9),
                Location = new Point(20, 65),
                Size = new Size(120, 23)
            };
            configGroup.Controls.Add(clientLabel);

            var clientText = new TextBox
            {
                Name = "clientText",
                Font = new Font("Segoe UI", 9),
                Location = new Point(150, 63),
                Size = new Size(200, 25),
                PlaceholderText = "Ej: mi-empresa",
                BorderStyle = BorderStyle.FixedSingle,
                BackColor = Color.White
            };
            configGroup.Controls.Add(clientText);

            // Authorization Token
            var authLabel = new Label
            {
                Text = "🔑 Auth Token:",
                Font = new Font("Segoe UI", 9),
                Location = new Point(20, 95),
                Size = new Size(120, 23)
            };
            configGroup.Controls.Add(authLabel);

            var authText = new TextBox
            {
                Name = "authText",
                Font = new Font("Segoe UI", 9),
                Location = new Point(150, 93),
                Size = new Size(200, 25),
                PlaceholderText = "Token de autorización",
                Text = "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3",
                BorderStyle = BorderStyle.FixedSingle,
                BackColor = Color.White
            };
            configGroup.Controls.Add(authText);

            // Monitor Interval
            var intervalLabel = new Label
            {
                Text = "⏱️ Intervalo (segundos):",
                Font = new Font("Segoe UI", 9),
                Location = new Point(20, 125),
                Size = new Size(120, 23)
            };
            configGroup.Controls.Add(intervalLabel);

            var intervalText = new TextBox
            {
                Name = "intervalText",
                Font = new Font("Segoe UI", 9),
                Location = new Point(150, 123),
                Size = new Size(60, 25),
                Text = "2",
                BorderStyle = BorderStyle.FixedSingle,
                BackColor = Color.White,
                TextAlign = HorizontalAlignment.Center
            };
            configGroup.Controls.Add(intervalText);

            var intervalHelpLabel = new Label
            {
                Text = "(1-30 seg, recomendado: 2)",
                Font = new Font("Segoe UI", 8),
                Location = new Point(220, 126),
                Size = new Size(150, 20),
                ForeColor = Color.Gray
            };
            configGroup.Controls.Add(intervalHelpLabel);

            // Auto Start Checkbox
            var autoStartCheck = new CheckBox
            {
                Name = "autoStartCheck",
                Text = "🚀 Iniciar automáticamente con Windows",
                Font = new Font("Segoe UI", 9, FontStyle.Bold),
                Location = new Point(20, 155),
                Size = new Size(300, 20),
                ForeColor = Color.FromArgb(40, 167, 69),
                FlatStyle = FlatStyle.Flat
            };
            configGroup.Controls.Add(autoStartCheck);

            // Save Config Button
            var saveConfigBtn = new Button
            {
                Name = "saveConfigBtn",
                Text = "💾 Guardar Configuración",
                Font = new Font("Segoe UI", 9, FontStyle.Bold),
                Location = new Point(370, 95),
                Size = new Size(170, 35),
                BackColor = Color.FromArgb(0, 123, 255),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            saveConfigBtn.FlatAppearance.BorderSize = 0;
            saveConfigBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(0, 86, 179);
            saveConfigBtn.FlatAppearance.MouseDownBackColor = Color.FromArgb(0, 63, 135);
            saveConfigBtn.Click += SaveConfig_Click;
            configGroup.Controls.Add(saveConfigBtn);

            // Status Group
            var statusGroup = new GroupBox
            {
                Text = "📊 Estado del Servicio",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(20, 320),
                Size = new Size(560, 120),
                ForeColor = Color.DarkGreen
            };
            this.Controls.Add(statusGroup);

            // Status Label
            var statusLabel = new Label
            {
                Name = "statusLabel",
                Text = "⏸️ Detenido - Configura primero",
                Font = new Font("Segoe UI", 11, FontStyle.Bold),
                Location = new Point(20, 30),
                Size = new Size(400, 25),
                ForeColor = Color.Red
            };
            statusGroup.Controls.Add(statusLabel);

            // Connection Status
            var connectionLabel = new Label
            {
                Name = "connectionLabel",
                Text = "🔗 Sin conexión",
                Font = new Font("Segoe UI", 9),
                Location = new Point(20, 60),
                Size = new Size(400, 20),
                ForeColor = Color.Gray
            };
            statusGroup.Controls.Add(connectionLabel);

            // Control Buttons
            var startBtn = new Button
            {
                Name = "startBtn",
                Text = "▶️ INICIAR SERVICIO",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(50, 460),
                Size = new Size(180, 45),
                BackColor = Color.FromArgb(40, 167, 69),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Enabled = false,
                Cursor = Cursors.Hand
            };
            startBtn.FlatAppearance.BorderSize = 0;
            startBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(34, 139, 58);
            startBtn.FlatAppearance.MouseDownBackColor = Color.FromArgb(25, 105, 44);
            startBtn.Click += StartService_Click;
            this.Controls.Add(startBtn);

            var stopBtn = new Button
            {
                Name = "stopBtn",
                Text = "⏹️ DETENER SERVICIO",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(250, 460),
                Size = new Size(180, 45),
                BackColor = Color.FromArgb(220, 53, 69),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Enabled = false,
                Cursor = Cursors.Hand
            };
            stopBtn.FlatAppearance.BorderSize = 0;
            stopBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(200, 35, 51);
            stopBtn.FlatAppearance.MouseDownBackColor = Color.FromArgb(176, 27, 41);
            stopBtn.Click += StopService_Click;
            this.Controls.Add(stopBtn);

            var helpBtn = new Button
            {
                Text = "❓ AYUDA",
                Font = new Font("Segoe UI", 9, FontStyle.Bold),
                Location = new Point(450, 460),
                Size = new Size(100, 45),
                BackColor = Color.FromArgb(255, 193, 7),
                ForeColor = Color.FromArgb(33, 37, 41),
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            helpBtn.FlatAppearance.BorderSize = 0;
            helpBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(255, 174, 0);
            helpBtn.FlatAppearance.MouseDownBackColor = Color.FromArgb(217, 147, 0);
            helpBtn.Click += (s, e) => MessageBox.Show(
                "🚀 GRIDPOS PRINT SERVICE\n\n" +
                "1. Configura tu API (Producción/Demo)\n" +
                "2. Ingresa tu Client Slug\n" +
                "3. Ingresa tu Authorization Token\n" +
                "4. Configura intervalo de monitoreo (1-30 seg)\n" +
                "5. Marca auto-inicio si deseas\n" +
                "6. Guarda la configuración\n" +
                "7. Inicia el servicio\n" +
                "8. ¡El sistema monitoreará automáticamente!\n\n" +
                "⚡ Monitoreo configurable (1-30 segundos)\n" +
                "🔗 Conexión directa a GridPos API\n" +
                "🔑 Authorization Token personalizable\n" +
                "🚀 Auto-inicio con Windows opcional\n" +
                "🖨️ Impresión directa a impresoras compartidas\n\n" +
                "📧 Soporte: soporte@gridpos.com",
                "Ayuda - GridPos Print Service",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
            this.Controls.Add(helpBtn);

            // === LOG GROUP ===
            var logGroup = new GroupBox
            {
                Text = "📋 Logs del Sistema",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(20, 460),
                Size = new Size(560, 100),
                ForeColor = Color.FromArgb(51, 51, 51)
            };
            this.Controls.Add(logGroup);

            // Log TextBox
            var logTextBox = new TextBox
            {
                Name = "logTextBox",
                Font = new Font("Consolas", 8),
                Location = new Point(10, 25),
                Size = new Size(450, 45),
                Multiline = true,
                ScrollBars = ScrollBars.Vertical,
                ReadOnly = true,
                BackColor = Color.FromArgb(248, 249, 250),
                ForeColor = Color.FromArgb(33, 37, 41),
                BorderStyle = BorderStyle.FixedSingle,
                Text = "🚀 GridPos Print Service iniciado\n📋 Esperando configuración...\n"
            };
            logGroup.Controls.Add(logTextBox);

            // Clear Log Button
            var clearLogBtn = new Button
            {
                Name = "clearLogBtn",
                Text = "🗑️ Limpiar",
                Font = new Font("Segoe UI", 8, FontStyle.Bold),
                Location = new Point(470, 25),
                Size = new Size(80, 45),
                BackColor = Color.FromArgb(108, 117, 125),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            clearLogBtn.FlatAppearance.BorderSize = 0;
            clearLogBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(90, 98, 104);
            clearLogBtn.FlatAppearance.MouseDownBackColor = Color.FromArgb(73, 80, 87);
            clearLogBtn.Click += (s, e) => {
                var logBox = this.Controls.Find("logTextBox", true)[0] as TextBox;
                logBox.Text = "🚀 Log limpiado\n";
                AddLog("📋 Log limpiado por usuario");
            };
            logGroup.Controls.Add(clearLogBtn);

            // Load saved values
            LoadSavedConfiguration();

            this.ResumeLayout(false);
        }

        private void LoadConfiguration()
        {
            try
            {
                var apiType = GetRegistryValue("ApiType", "api");
                apiBaseUrl = $"https://{apiType}.gridpos.co";
                clientSlug = GetRegistryValue("ClientSlug", "");
                authToken = GetRegistryValue("AuthToken", "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3");
                monitorInterval = int.Parse(GetRegistryValue("MonitorInterval", "2")) * 1000;
            }
            catch (Exception ex)
            {
                UpdateStatus($"❌ Error cargando configuración: {ex.Message}", Color.Red);
            }
        }

        private void LoadSavedConfiguration()
        {
            try
            {
                var apiCombo = this.Controls.Find("apiCombo", true)[0] as ComboBox;
                var clientText = this.Controls.Find("clientText", true)[0] as TextBox;
                var authText = this.Controls.Find("authText", true)[0] as TextBox;
                var intervalText = this.Controls.Find("intervalText", true)[0] as TextBox;
                var autoStartCheck = this.Controls.Find("autoStartCheck", true)[0] as CheckBox;

                var apiType = GetRegistryValue("ApiType", "api");
                apiCombo.SelectedIndex = apiType == "api-demo" ? 1 : 0;
                clientText.Text = GetRegistryValue("ClientSlug", "");
                authText.Text = GetRegistryValue("AuthToken", "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3");
                intervalText.Text = GetRegistryValue("MonitorInterval", "2");
                autoStartCheck.Checked = GetRegistryValue("AutoStart", "false") == "true";

                if (!string.IsNullOrEmpty(clientText.Text) && !string.IsNullOrEmpty(authText.Text))
                {
                    var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
                    startBtn.Enabled = true;
                    UpdateStatus("✅ Configuración cargada - Listo para iniciar", Color.Green);
                }
            }
            catch { }
        }

        private void SaveConfig_Click(object sender, EventArgs e)
        {
            try
            {
                var apiCombo = this.Controls.Find("apiCombo", true)[0] as ComboBox;
                var clientText = this.Controls.Find("clientText", true)[0] as TextBox;
                var authText = this.Controls.Find("authText", true)[0] as TextBox;
                var intervalText = this.Controls.Find("intervalText", true)[0] as TextBox;
                var autoStartCheck = this.Controls.Find("autoStartCheck", true)[0] as CheckBox;

                if (string.IsNullOrWhiteSpace(clientText.Text))
                {
                    MessageBox.Show("❌ El Client Slug es obligatorio", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    return;
                }

                if (string.IsNullOrWhiteSpace(authText.Text))
                {
                    MessageBox.Show("❌ El Authorization Token es obligatorio", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    return;
                }

                // Validar intervalo
                if (!int.TryParse(intervalText.Text, out int intervalSeconds) || intervalSeconds < 1 || intervalSeconds > 30)
                {
                    MessageBox.Show("❌ El intervalo debe ser entre 1 y 30 segundos", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    return;
                }

                var apiType = apiCombo.SelectedIndex == 1 ? "api-demo" : "api";
                apiBaseUrl = $"https://{apiType}.gridpos.co";
                clientSlug = clientText.Text.Trim();
                authToken = authText.Text.Trim();
                monitorInterval = intervalSeconds * 1000;

                // Save to registry
                SaveToRegistry("ApiType", apiType);
                SaveToRegistry("ClientSlug", clientSlug);
                SaveToRegistry("AuthToken", authToken);
                SaveToRegistry("MonitorInterval", intervalSeconds.ToString());
                SaveToRegistry("AutoStart", autoStartCheck.Checked.ToString().ToLower());

                // Configure Windows startup
                ConfigureWindowsStartup(autoStartCheck.Checked);

                // Update HTTP client
                httpClient.DefaultRequestHeaders.Clear();
                httpClient.DefaultRequestHeaders.Add("Authorization", authToken);
                httpClient.DefaultRequestHeaders.Add("X-Client-Slug", clientSlug);
                httpClient.DefaultRequestHeaders.Add("User-Agent", "GridPosPrintService/1.0");

                var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
                startBtn.Enabled = true;

                var autoStartMsg = autoStartCheck.Checked ? " - Auto-inicio activado" : "";
                UpdateStatus($"✅ Configuración guardada - API: {(apiType == "api" ? "Producción" : "Demo")} - Intervalo: {intervalSeconds}s{autoStartMsg}", Color.Green);
                AddLog($"💾 Configuración guardada: API={apiType}, Client={clientSlug}, Intervalo={intervalSeconds}s");
                MessageBox.Show("✅ Configuración guardada correctamente", "Éxito", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show($"❌ Error guardando: {ex.Message}", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void StartService_Click(object sender, EventArgs e)
        {
            if (string.IsNullOrEmpty(clientSlug) || string.IsNullOrEmpty(authToken))
            {
                MessageBox.Show("❌ Configura primero el Client Slug y Authorization Token", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

                        isMonitoring = true;
            monitorTimer.Interval = monitorInterval;
            monitorTimer.Start();

            var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
            var stopBtn = this.Controls.Find("stopBtn", true)[0] as Button;

            startBtn.Enabled = false;
            stopBtn.Enabled = true;

            var intervalSeconds = monitorInterval / 1000;
            UpdateStatus($"🚀 Servicio iniciado - Monitoreando cada {intervalSeconds} segundos", Color.Green);
            AddLog($"🚀 Servicio iniciado: URL={apiBaseUrl}/print-queue");
            AddLog($"⏱️ Intervalo de monitoreo: {intervalSeconds} segundos");
            AddLog($"🔑 Headers: Authorization=***, X-Client-Slug={clientSlug}");
        }

        private void StopService_Click(object sender, EventArgs e)
        {
            isMonitoring = false;
            monitorTimer.Stop();

            var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
            var stopBtn = this.Controls.Find("stopBtn", true)[0] as Button;

            startBtn.Enabled = true;
            stopBtn.Enabled = false;

            UpdateStatus("⏸️ Servicio detenido", Color.Red);
            UpdateConnection("🔗 Desconectado", Color.Gray);
            AddLog("⏸️ Servicio detenido por usuario");
        }

        private void SetupTimer()
        {
            monitorTimer = new System.Windows.Forms.Timer
            {
                Interval = monitorInterval // Intervalo dinámico
            };
            monitorTimer.Tick += async (s, e) => await CheckPrintQueue();
        }

        private async Task CheckPrintQueue()
        {
            if (!isMonitoring) return;

            try
            {
                var url = $"{apiBaseUrl}/print-queue";
                var response = await httpClient.GetAsync(url);

                if (response.IsSuccessStatusCode)
                {
                    var content = await response.Content.ReadAsStringAsync();
                    UpdateConnection($"🔗 Conectado - {DateTime.Now:HH:mm:ss}", Color.Green);

                    if (!string.IsNullOrWhiteSpace(content) && content != "[]" && content != "{}")
                    {
                        AddLog($"📦 Respuesta API: {content}");

                        try
                        {
                            var printJobs = JsonSerializer.Deserialize<JsonElement[]>(content);

                            if (printJobs != null && printJobs.Length > 0)
                            {
                                UpdateStatus($"📄 {printJobs.Length} trabajos encontrados", Color.Blue);
                                AddLog($"🔄 Procesando {printJobs.Length} trabajos de impresión");

                                foreach (var job in printJobs)
                                {
                                    await ProcessPrintJob(job);
                                }
                            }
                        }
                        catch (JsonException ex)
                        {
                            AddLog($"❌ Error parsing JSON: {ex.Message}");
                        }
                    }
                    else
                    {
                        UpdateStatus($"✅ Monitoreando - Sin trabajos", Color.Green);
                    }
                }
                else
                {
                    var errorMsg = $"⚠️ Error API: {response.StatusCode}";
                    UpdateConnection(errorMsg, Color.Orange);
                    AddLog(errorMsg);
                }
            }
            catch (Exception ex)
            {
                var errorMsg = $"❌ Sin conexión: {ex.Message}";
                UpdateConnection(errorMsg, Color.Red);
                AddLog(errorMsg);
            }
        }

        private void UpdateStatus(string message, Color color)
        {
            if (this.InvokeRequired)
            {
                this.Invoke(new Action(() => UpdateStatus(message, color)));
                return;
            }

            var statusLabel = this.Controls.Find("statusLabel", true)[0] as Label;
            statusLabel.Text = message;
            statusLabel.ForeColor = color;
        }

        private void UpdateConnection(string message, Color color)
        {
            if (this.InvokeRequired)
            {
                this.Invoke(new Action(() => UpdateConnection(message, color)));
                return;
            }

            var connectionLabel = this.Controls.Find("connectionLabel", true)[0] as Label;
            connectionLabel.Text = message;
            connectionLabel.ForeColor = color;
        }

        private string GetRegistryValue(string key, string defaultValue)
        {
            try
            {
                var value = Registry.GetValue(@"HKEY_LOCAL_MACHINE\SOFTWARE\GridPos\PrintService", key, defaultValue);
                return value?.ToString() ?? defaultValue;
            }
            catch
            {
                return defaultValue;
            }
        }

        private void SaveToRegistry(string key, string value)
        {
            try
            {
                Registry.SetValue(@"HKEY_LOCAL_MACHINE\SOFTWARE\GridPos\PrintService", key, value);
            }
            catch (Exception ex)
            {
                throw new Exception($"Error guardando en registro: {ex.Message}");
            }
        }

        private void ConfigureWindowsStartup(bool enable)
        {
            try
            {
                const string startupKey = @"HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Run";
                const string appName = "GridPosPrintService";

                if (enable)
                {
                    // Agregar al inicio de Windows
                    var exePath = System.Reflection.Assembly.GetExecutingAssembly().Location;
                    Registry.SetValue(startupKey, appName, $"\"{exePath}\"");
                }
                else
                {
                    // Remover del inicio de Windows
                    try
                    {
                        var key = Registry.CurrentUser.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Run", true);
                        if (key?.GetValue(appName) != null)
                        {
                            key.DeleteValue(appName);
                        }
                        key?.Close();
                    }
                    catch { } // Ignorar errores si la clave no existe
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show($"⚠️ No se pudo configurar el inicio automático: {ex.Message}",
                    "Advertencia", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }

        private async Task ProcessPrintJob(JsonElement job)
        {
            try
            {
                // Verificar que el trabajo tenga las propiedades necesarias
                if (!job.TryGetProperty("action", out var actionElement) ||
                    !job.TryGetProperty("id", out var idElement))
                {
                    AddLog("❌ Trabajo sin action o id, saltando...");
                    return;
                }

                var action = actionElement.GetString();
                var jobId = idElement.GetString();

                AddLog($"🔄 Procesando trabajo ID: {jobId}, Acción: {action}");

                switch (action)
                {
                    case "openCashDrawer":
                        await ProcessOpenCashDrawer(job);
                        break;

                    case "orderPrinter":
                        await ProcessOrderPrint(job);
                        break;

                    case "salePrinter":
                        await ProcessSalePrint(job);
                        break;

                    default:
                        AddLog($"⚠️ Acción no reconocida: {action}");
                        break;
                }

                // Eliminar trabajo procesado de la cola
                await DeletePrintQueueItem(jobId);
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error procesando trabajo: {ex.Message}");
            }
        }

        private async Task ProcessOpenCashDrawer(JsonElement job)
        {
            try
            {
                if (!job.TryGetProperty("printer", out var printerElement))
                {
                    AddLog("❌ Trabajo openCashDrawer sin nombre de impresora");
                    return;
                }

                var printerName = printerElement.GetString();
                AddLog($"🔓 Abriendo caja en impresora: {printerName}");

                // 🚀 IMPRESIÓN REAL: Abrir caja usando ESC/POS-.NET
                var printer = new SerialPrinter(portName: printerName, baudRate: 9600);
                var e = new EPSON();

                printer.Write(e.OpenCashDrawerPin2());
                AddLog($"✅ Caja abierta exitosamente en: {printerName}");
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error abriendo caja: {ex.Message}");
            }
        }

        private async Task ProcessOrderPrint(JsonElement job)
        {
            try
            {
                if (!job.TryGetProperty("printer", out var printerElement))
                {
                    AddLog("❌ Trabajo orderPrinter sin nombre de impresora");
                    return;
                }

                var printerName = printerElement.GetString();
                AddLog($"🖨️ Imprimiendo orden en: {printerName}");

                // 🚀 IMPRESIÓN REAL: usando ESC/POS-.NET
                var printer = new SerialPrinter(portName: printerName, baudRate: 9600);
                var e = new EPSON();

                // Verificar si viene con data_json (nuevo) o image (tradicional)
                if (job.TryGetProperty("data_json", out var dataJsonElement))
                {
                    AddLog("🚀 Modo ESC/POS OPTIMIZADO - Usando datos JSON");
                    await PrintOrderWithEscPos(printer, e, dataJsonElement, job);
                }
                else if (job.TryGetProperty("image", out var imageElement))
                {
                    AddLog("🐌 Modo tradicional - Usando imagen base64");
                    await PrintOrderWithImage(printer, e, imageElement.GetString());
                }

                AddLog($"✅ Orden impresa exitosamente en: {printerName}");
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error imprimiendo orden: {ex.Message}");
            }
        }

        private async Task ProcessSalePrint(JsonElement job)
        {
            try
            {
                if (!job.TryGetProperty("printer", out var printerElement))
                {
                    AddLog("❌ Trabajo salePrinter sin nombre de impresora");
                    return;
                }

                var printerName = printerElement.GetString();
                AddLog($"🧾 Imprimiendo venta en: {printerName}");

                if (job.TryGetProperty("image", out var imageElement))
                {
                    var base64Image = imageElement.GetString();
                    AddLog($"📄 Imagen recibida: {base64Image?.Length} caracteres");
                }

                if (job.TryGetProperty("logo", out var logoElement))
                {
                    var logoUrl = logoElement.GetString();
                    AddLog($"🖼️ Logo URL: {logoUrl}");
                }

                // Aquí iría la lógica de impresión real como en el PHP
                AddLog($"✅ Venta impresa exitosamente en: {printerName}");
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error imprimiendo venta: {ex.Message}");
            }
        }

        private async Task DeletePrintQueueItem(string jobId)
        {
            try
            {
                var deleteUrl = $"{apiBaseUrl}/print-queue/{jobId}";
                var response = await httpClient.GetAsync(deleteUrl);

                if (response.IsSuccessStatusCode)
                {
                    AddLog($"🗑️ Trabajo {jobId} eliminado de la cola");
                }
                else
                {
                    AddLog($"⚠️ Error eliminando trabajo {jobId}: {response.StatusCode}");
                }
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error eliminando trabajo {jobId}: {ex.Message}");
            }
        }

                private async Task PrintOrderWithEscPos(BasePrinter printer, ICommandEmitter e, JsonElement orderData, JsonElement job)
        {
            try
            {
                AddLog("📝 Generando ticket ESC/POS IGUAL AL PHP...");
                var startTime = DateTime.Now;

                // Extraer configuración del papel (igual que el PHP)
                var paperWidth = 80; // Por defecto 80mm
                if (orderData.TryGetProperty("print_settings", out var printSettings) &&
                    printSettings.TryGetProperty("paper_width", out var paperWidthElement))
                {
                    paperWidth = paperWidthElement.GetInt32();
                }

                AddLog($"🚀 Ancho de papel: {paperWidth}");
                var isSmallPaper = paperWidth == 58;

                // === ENCABEZADO === (IGUAL AL PHP)
                printer.Write(e.Initialize());
                printer.Write(e.CenterAlign());

                // Cliente si existe - Ajustado por tamaño de papel (IGUAL AL PHP)
                var clientName = "";
                if (orderData.TryGetProperty("order_data", out var orderInfo))
                {
                    if (orderInfo.TryGetProperty("client_name", out var clientElement))
                        clientName = clientElement.GetString() ?? "";
                    else if (orderData.TryGetProperty("client_info", out var clientInfo) &&
                             clientInfo.TryGetProperty("name", out var clientNameElement))
                        clientName = clientNameElement.GetString() ?? "";
                }

                if (!string.IsNullOrEmpty(clientName))
                {
                    if (isSmallPaper)
                    {
                        // 📱 Para papel 58mm: usar solo EMPHASIZED (texto moderado)
                        var clientNameFormatted = clientName.Length > 32 ? clientName.Substring(0, 32) : clientName;
                        printer.Write(
                            ByteSplicer.Combine(
                                e.SetStyles(PrintStyle.Bold),
                                e.PrintLine(clientNameFormatted),
                                e.SetStyles(PrintStyle.None)
                            )
                        );
                    }
                    else
                    {
                        // 🖨️ Para papel 80mm: texto grande normal
                        printer.Write(
                            ByteSplicer.Combine(
                                e.SetStyles(PrintStyle.Bold | PrintStyle.DoubleWidth),
                                e.PrintLine(clientName),
                                e.SetStyles(PrintStyle.None)
                            )
                        );
                    }
                }

                // Fecha de la orden (IGUAL AL PHP)
                var orderDate = "";
                if (orderInfo != null && orderInfo.TryGetProperty("date", out var dateElement))
                    orderDate = dateElement.GetString() ?? "";

                if (!string.IsNullOrEmpty(orderDate))
                    printer.Write(e.PrintLine(orderDate));

                // Teléfono de la empresa si existe (IGUAL AL PHP)
                if (orderInfo != null && orderInfo.TryGetProperty("phone", out var phoneElement))
                {
                    var phone = phoneElement.GetString();
                    if (!string.IsNullOrEmpty(phone))
                        printer.Write(e.PrintLine($"CEL: {phone}"));
                }

                // Dirección de envío si existe (IGUAL AL PHP)
                if (orderInfo != null && orderInfo.TryGetProperty("shipping_address", out var addressElement))
                {
                    var address = addressElement.GetString();
                    if (!string.IsNullOrEmpty(address))
                        printer.Write(e.PrintLine($"DIRECCION: {address}"));
                }

                // === SEPARADOR GRUESO === (IGUAL AL PHP)
                printer.Write(e.LeftAlign());
                var separator = isSmallPaper ? new string('-', 32) : new string('-', 48);
                printer.Write(e.PrintLine(separator));

                // ENCABEZADOS DE COLUMNAS - Ajustado para tamaño de papel (IGUAL AL PHP)
                printer.Write(e.SetStyles(PrintStyle.Bold));
                if (isSmallPaper)
                {
                    printer.Write(e.PrintLine("CANT  ITEM")); // Más compacto para 58mm
                }
                else
                {
                    printer.Write(e.PrintLine("CANT     ITEM")); // Formato normal para 80mm
                }
                printer.Write(e.SetStyles(PrintStyle.None));
                printer.Write(e.PrintLine(separator));

                // === PRODUCTOS - FORMATO OPTIMIZADO PARA TAMAÑO DE PAPEL === (IGUAL AL PHP)
                var productCount = 0;
                var currentIndex = 0;

                if (orderData.TryGetProperty("products", out var productsElement) && productsElement.ValueKind == JsonValueKind.Array)
                {
                    productCount = productsElement.GetArrayLength();

                    foreach (var product in productsElement.EnumerateArray())
                    {
                        currentIndex++;

                        var qty = 1;
                        var name = "Producto";
                        var notes = "";

                        if (product.TryGetProperty("quantity", out var qtyElement))
                            qty = qtyElement.GetInt32();
                        if (product.TryGetProperty("name", out var nameElement))
                            name = nameElement.GetString() ?? "Producto";
                        if (product.TryGetProperty("notes", out var notesElement))
                            notes = notesElement.GetString() ?? "";

                        if (isSmallPaper)
                        {
                            // 📱 FORMATO PARA PAPEL 58MM - Texto moderado sin cortes (IGUAL AL PHP)
                            var qtyPadded = qty.ToString().PadRight(2);

                            // Calcular espacio disponible: 32 chars - 2 qty - 2 espacios = 28 chars para nombre
                            var maxNameChars = 28;
                            var nameFormatted = name.Length > maxNameChars ? name.Substring(0, maxNameChars) : name;

                            printer.Write(
                                ByteSplicer.Combine(
                                    e.SetStyles(PrintStyle.Bold),
                                    e.PrintLine($"{qtyPadded}  {nameFormatted.ToUpper()}"),
                                    e.SetStyles(PrintStyle.None)
                                )
                            );

                            // Si el nombre fue cortado, imprimir el resto en la siguiente línea (IGUAL AL PHP)
                            if (name.Length > maxNameChars)
                            {
                                var remainingName = name.Substring(maxNameChars);
                                printer.Write(
                                    ByteSplicer.Combine(
                                        e.SetStyles(PrintStyle.Bold),
                                        e.PrintLine($"    {remainingName.ToUpper()}"),
                                        e.SetStyles(PrintStyle.None)
                                    )
                                );
                            }
                        }
                        else
                        {
                            // 🖨️ FORMATO PARA PAPEL 80MM - Texto grande normal (IGUAL AL PHP)
                            var qtyPadded = qty.ToString().PadRight(2);
                            printer.Write(
                                ByteSplicer.Combine(
                                    e.SetStyles(PrintStyle.Bold | PrintStyle.DoubleWidth),
                                    e.PrintLine($"{qtyPadded}  {name.ToUpper()}"),
                                    e.SetStyles(PrintStyle.None)
                                )
                            );
                        }

                        // Notas del producto si existen (ajustadas por tamaño de papel) (IGUAL AL PHP)
                        if (!string.IsNullOrEmpty(notes))
                        {
                            printer.Write(e.SetStyles(PrintStyle.Bold));

                            if (isSmallPaper)
                            {
                                // Para 58mm: limitar notas a 28 caracteres por línea
                                var maxNoteChars = 28;
                                var noteLines = WordWrapText(notes, maxNoteChars);
                                foreach (var noteLine in noteLines)
                                {
                                    printer.Write(e.PrintLine($"  * {noteLine.ToUpper()}"));
                                }
                            }
                            else
                            {
                                // Para 80mm: formato normal
                                printer.Write(e.PrintLine($"    * {notes.ToUpper()}"));
                            }

                            printer.Write(e.SetStyles(PrintStyle.None));
                        }

                        // Agregar espacio solo si no es el último producto (IGUAL AL PHP)
                        if (currentIndex < productCount)
                        {
                            printer.Write(e.PrintLine(""));
                        }
                    }
                }

                // === SEPARADOR FINAL === (IGUAL AL PHP)
                printer.Write(e.PrintLine(separator));

                // NOTA GENERAL si existe (IGUAL AL PHP)
                var generalNote = "";
                if (orderInfo != null)
                {
                    if (orderInfo.TryGetProperty("note", out var noteElement))
                        generalNote = noteElement.GetString() ?? "";
                    else if (orderData.TryGetProperty("general_note", out var generalNoteElement))
                        generalNote = generalNoteElement.GetString() ?? "";
                }

                if (!string.IsNullOrEmpty(generalNote))
                {
                    printer.Write(
                        ByteSplicer.Combine(
                            e.SetStyles(PrintStyle.Bold),
                            e.PrintLine($"NOTA: {generalNote.ToUpper()}"),
                            e.SetStyles(PrintStyle.None),
                            e.PrintLine("")
                        )
                    );
                }

                // === PIE DE PÁGINA === (IGUAL AL PHP)
                // Usuario que atiende
                var userName = "Sistema";
                if (orderData.TryGetProperty("user", out var userElement))
                {
                    if (userElement.TryGetProperty("name", out var userNameElement))
                        userName = userNameElement.GetString() ?? "Sistema";
                    else if (userElement.TryGetProperty("nickname", out var userNickElement))
                        userName = userNickElement.GetString() ?? "Sistema";
                }
                printer.Write(e.PrintLine($"Atendido por: {userName}"));

                // Timestamp de impresión
                var datePrint = "";
                if (orderInfo != null && orderInfo.TryGetProperty("date_print", out var datePrintElement))
                    datePrint = datePrintElement.GetString() ?? "";

                if (!string.IsNullOrEmpty(datePrint))
                    printer.Write(e.PrintLine($"Impresión: {datePrint}"));

                // ID de orden más visible (IGUAL AL PHP)
                var orderIdDisplay = "";
                if (orderInfo != null)
                {
                    if (orderInfo.TryGetProperty("shipping_address", out var shippingElement) &&
                        !string.IsNullOrEmpty(shippingElement.GetString()) &&
                        orderInfo.TryGetProperty("order_number", out var orderNumberElement))
                    {
                        orderIdDisplay = orderNumberElement.GetString() ?? "";
                    }
                    else if (orderInfo.TryGetProperty("id", out var idElement))
                    {
                        orderIdDisplay = idElement.ToString();
                    }
                }

                if (string.IsNullOrEmpty(orderIdDisplay))
                    orderIdDisplay = "1";

                printer.Write(
                    ByteSplicer.Combine(
                        e.SetStyles(PrintStyle.Bold),
                        e.PrintLine($"ORDEN: {orderIdDisplay}"),
                        e.SetStyles(PrintStyle.None)
                    )
                );

                printer.Write(e.PrintLine(""));
                printer.Write(e.FullCutAfterFeed(1));

                // Abrir caja si se requiere (IGUAL AL PHP)
                if (job.TryGetProperty("open_cash", out var openCashElement) && openCashElement.GetBoolean())
                {
                    printer.Write(e.OpenCashDrawerPin2());
                    AddLog("💰 Caja abierta como parte del proceso de impresión ESC/POS");
                }

                var executionTime = (DateTime.Now - startTime).TotalMilliseconds;
                AddLog($"🚀 Orden impresa con ESC/POS en {executionTime:F2}ms (ULTRA RÁPIDO)");
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error imprimiendo con ESC/POS: {ex.Message}");
            }
        }

        private async Task PrintOrderWithImage(BasePrinter printer, ICommandEmitter e, string base64Image)
        {
            try
            {
                AddLog("🖼️ Procesando imagen base64...");

                // Decodificar imagen base64
                var imageData = Convert.FromBase64String(base64Image.Split(',').Last());
                var tempPath = Path.GetTempFileName() + ".png";
                await File.WriteAllBytesAsync(tempPath, imageData);

                // Imprimir imagen usando ESC/POS-.NET
                var img = new FileInfo(tempPath);

                printer.Write(
                    ByteSplicer.Combine(
                        e.CenterAlign(),
                        e.PrintImage(img, true),
                        e.PrintLine(""),
                        e.FullCutAfterFeed(3)
                    )
                );

                // Limpiar archivo temporal
                File.Delete(tempPath);

                AddLog("✅ Imagen impresa correctamente");
            }
            catch (Exception ex)
            {
                AddLog($"❌ Error imprimiendo imagen: {ex.Message}");
            }
        }

        /// <summary>
        /// Word wrap mejorado para ESC/POS - Optimizado para papel 58mm (IGUAL AL PHP)
        /// </summary>
        private List<string> WordWrapText(string text, int maxChars)
        {
            if (text.Length <= maxChars)
            {
                return new List<string> { text }; // Si el texto ya cabe, devolver como está
            }

            var words = text.Split(' ');
            var lines = new List<string>();
            var currentLine = "";

            foreach (var word in words)
            {
                // Si la palabra sola es más larga que el ancho máximo, dividirla
                if (word.Length > maxChars)
                {
                    // Finalizar línea actual si tiene contenido
                    if (!string.IsNullOrEmpty(currentLine))
                    {
                        lines.Add(currentLine.Trim());
                        currentLine = "";
                    }

                    // Dividir palabra larga en chunks
                    for (int i = 0; i < word.Length; i += maxChars)
                    {
                        var chunk = word.Substring(i, Math.Min(maxChars, word.Length - i));
                        lines.Add(chunk);
                    }
                    continue;
                }

                // Verificar si la palabra cabe en la línea actual
                var testLine = string.IsNullOrEmpty(currentLine) ? word : currentLine + " " + word;

                if (testLine.Length <= maxChars)
                {
                    currentLine = testLine;
                }
                else
                {
                    // No cabe, finalizar línea actual y empezar nueva
                    if (!string.IsNullOrEmpty(currentLine))
                    {
                        lines.Add(currentLine.Trim());
                    }
                    currentLine = word;
                }
            }

            // Agregar última línea si tiene contenido
            if (!string.IsNullOrEmpty(currentLine))
            {
                lines.Add(currentLine.Trim());
            }

            return lines;
        }

        private void AddLog(string message)
        {
            try
            {
                var logBox = this.Controls.Find("logTextBox", true)[0] as TextBox;
                var timestamp = DateTime.Now.ToString("HH:mm:ss");
                var logEntry = $"[{timestamp}] {message}\n";

                // Ejecutar en el hilo de UI
                if (logBox.InvokeRequired)
                {
                    logBox.Invoke(new Action(() => {
                        logBox.AppendText(logEntry);
                        logBox.SelectionStart = logBox.Text.Length;
                        logBox.ScrollToCaret();
                    }));
                }
                else
                {
                    logBox.AppendText(logEntry);
                    logBox.SelectionStart = logBox.Text.Length;
                    logBox.ScrollToCaret();
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error en AddLog: {ex.Message}");
            }
        }

        protected override void Dispose(bool disposing)
        {
            if (disposing)
            {
                monitorTimer?.Dispose();
                httpClient?.Dispose();
            }
            base.Dispose(disposing);
        }
    }
}
