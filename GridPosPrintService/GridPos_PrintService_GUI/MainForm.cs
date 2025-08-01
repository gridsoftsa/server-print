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
        private Timer monitorTimer;
        private string apiBaseUrl = "";
        private string clientSlug = "";
        private string authToken = "f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3";
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
            this.ClientSize = new Size(600, 500);
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
                Size = new Size(560, 120),
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
                DropDownStyle = ComboBoxStyle.DropDownList
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
                PlaceholderText = "Ej: mi-empresa"
            };
            configGroup.Controls.Add(clientText);

            // Save Config Button
            var saveConfigBtn = new Button
            {
                Name = "saveConfigBtn",
                Text = "💾 Guardar Configuración",
                Font = new Font("Segoe UI", 9, FontStyle.Bold),
                Location = new Point(370, 45),
                Size = new Size(170, 35),
                BackColor = Color.LightBlue,
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            saveConfigBtn.Click += SaveConfig_Click;
            configGroup.Controls.Add(saveConfigBtn);

            // Status Group
            var statusGroup = new GroupBox
            {
                Text = "📊 Estado del Servicio",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(20, 240),
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
                Location = new Point(50, 380),
                Size = new Size(180, 45),
                BackColor = Color.LightGreen,
                FlatStyle = FlatStyle.Flat,
                Enabled = false,
                Cursor = Cursors.Hand
            };
            startBtn.Click += StartService_Click;
            this.Controls.Add(startBtn);

            var stopBtn = new Button
            {
                Name = "stopBtn",
                Text = "⏹️ DETENER SERVICIO",
                Font = new Font("Segoe UI", 10, FontStyle.Bold),
                Location = new Point(250, 380),
                Size = new Size(180, 45),
                BackColor = Color.LightCoral,
                FlatStyle = FlatStyle.Flat,
                Enabled = false,
                Cursor = Cursors.Hand
            };
            stopBtn.Click += StopService_Click;
            this.Controls.Add(stopBtn);

            var helpBtn = new Button
            {
                Text = "❓ AYUDA",
                Font = new Font("Segoe UI", 9),
                Location = new Point(450, 380),
                Size = new Size(100, 45),
                BackColor = Color.LightYellow,
                FlatStyle = FlatStyle.Flat,
                Cursor = Cursors.Hand
            };
            helpBtn.Click += (s, e) => MessageBox.Show(
                "🚀 GRIDPOS PRINT SERVICE\n\n" +
                "1. Configura tu API y Client Slug\n" +
                "2. Guarda la configuración\n" +
                "3. Inicia el servicio\n" +
                "4. ¡El sistema monitoreará automáticamente!\n\n" +
                "⚡ Monitoreo cada 2 segundos\n" +
                "🔗 Conexión directa a GridPos API\n\n" +
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

                var apiType = GetRegistryValue("ApiType", "api");
                apiCombo.SelectedIndex = apiType == "api-demo" ? 1 : 0;
                clientText.Text = GetRegistryValue("ClientSlug", "");

                if (!string.IsNullOrEmpty(clientText.Text))
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

                if (string.IsNullOrWhiteSpace(clientText.Text))
                {
                    MessageBox.Show("❌ El Client Slug es obligatorio", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    return;
                }

                var apiType = apiCombo.SelectedIndex == 1 ? "api-demo" : "api";
                apiBaseUrl = $"https://{apiType}.gridpos.co";
                clientSlug = clientText.Text.Trim();

                // Save to registry
                SaveToRegistry("ApiType", apiType);
                SaveToRegistry("ClientSlug", clientSlug);

                // Update HTTP client
                httpClient.DefaultRequestHeaders.Clear();
                httpClient.DefaultRequestHeaders.Add("Authorization", $"Bearer {authToken}");
                httpClient.DefaultRequestHeaders.Add("Client-Slug", clientSlug);
                httpClient.DefaultRequestHeaders.Add("User-Agent", "GridPosPrintService/1.0");

                var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
                startBtn.Enabled = true;

                UpdateStatus($"✅ Configuración guardada - API: {(apiType == "api" ? "Producción" : "Demo")}", Color.Green);
                MessageBox.Show("✅ Configuración guardada correctamente", "Éxito", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show($"❌ Error guardando: {ex.Message}", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void StartService_Click(object sender, EventArgs e)
        {
            if (string.IsNullOrEmpty(clientSlug))
            {
                MessageBox.Show("❌ Configura primero el Client Slug", "Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            isMonitoring = true;
            monitorTimer.Start();

            var startBtn = this.Controls.Find("startBtn", true)[0] as Button;
            var stopBtn = this.Controls.Find("stopBtn", true)[0] as Button;

            startBtn.Enabled = false;
            stopBtn.Enabled = true;

            UpdateStatus("🚀 Servicio iniciado - Monitoreando cada 2 segundos", Color.Green);
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
            monitorTimer = new Timer
            {
                Interval = 2000 // 2 segundos
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
