using System;
using System.Drawing;
using System.Net.Http;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Win32;
using System.Threading;

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
                "🚀 Auto-inicio con Windows opcional\n\n" +
                "📧 Soporte: soporte@gridpos.com",
                "Ayuda - GridPos Print Service",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
            this.Controls.Add(helpBtn);

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
                var response = await httpClient.GetAsync($"{apiBaseUrl}/print-queue");

                if (response.IsSuccessStatusCode)
                {
                    var content = await response.Content.ReadAsStringAsync();
                    UpdateConnection($"🔗 Conectado - {DateTime.Now:HH:mm:ss}", Color.Green);

                    if (!string.IsNullOrWhiteSpace(content) && content != "[]")
                    {
                        UpdateStatus($"📄 Trabajos encontrados - {DateTime.Now:HH:mm:ss}", Color.Blue);
                        // Aquí se procesarían los trabajos
                    }
                    else
                    {
                        UpdateStatus($"✅ Monitoreando - {DateTime.Now:HH:mm:ss}", Color.Green);
                    }
                }
                else
                {
                    UpdateConnection($"⚠️ Error API: {response.StatusCode}", Color.Orange);
                }
            }
            catch (Exception ex)
            {
                UpdateConnection($"❌ Sin conexión: {ex.Message}", Color.Red);
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
