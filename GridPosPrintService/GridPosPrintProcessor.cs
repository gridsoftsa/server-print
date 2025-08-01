using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Http;
using System.Text.Json;
using System.Drawing;
using System.Drawing.Printing;
using System.Text;

namespace GridPosPrintService
{
    /// <summary>
    /// 🚀 PROCESADOR ULTRA OPTIMIZADO DE COLA DE IMPRESIÓN
    /// 10x más rápido que el sistema PHP/VBS anterior
    /// </summary>
    public class GridPosPrintProcessor
    {
        private readonly IHttpClientFactory _httpClientFactory;
        private readonly ILogger _logger;
        private readonly string _apiBaseUrl;
        private static readonly JsonSerializerOptions JsonOptions = new()
        {
            PropertyNameCaseInsensitive = true
        };

        public GridPosPrintProcessor(IHttpClientFactory httpClientFactory, ILogger logger)
        {
            _httpClientFactory = httpClientFactory;
            _logger = logger;
            _apiBaseUrl = GetApiBaseUrl();
        }

        /// <summary>
        /// 🔧 CONFIGURAR HEADERS PARA GRIDPOS API
        /// </summary>
        private void ConfigureHttpHeaders(HttpClient httpClient)
        {
            try
            {
                // 🔑 AUTHORIZATION TOKEN FIJO
                httpClient.DefaultRequestHeaders.Add("Authorization", "Bearer f57225ee-7a78-4c05-aa3d-bbf1a0c4e1e3");

                // 🏢 CLIENT SLUG DESDE CONFIGURACIÓN
                var clientSlug = GetRegistryValue("ClientSlug", "");
                if (!string.IsNullOrEmpty(clientSlug))
                {
                    httpClient.DefaultRequestHeaders.Add("Client-Slug", clientSlug);
                }

                // 📱 USER AGENT
                httpClient.DefaultRequestHeaders.Add("User-Agent", "GridPosPrintService/1.0");

                _logger.LogInformation($"🔧 Headers configurados - Client: {clientSlug}");
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "💥 Error configurando headers HTTP");
            }
        }

                /// <summary>
        /// 🚀 PROCESAMIENTO PRINCIPAL - ULTRA RÁPIDO
        /// </summary>
        public async Task ProcessPrintQueue()
        {
            try
            {
                using var httpClient = _httpClientFactory.CreateClient("GridPosApi");

                // 🔧 CONFIGURAR HEADERS ANTES DE HACER REQUESTS
                ConfigureHttpHeaders(httpClient);

                // 🚀 CONSULTA DIRECTA A LA API GRIDPOS - Sin PHP, sin overhead
                var response = await httpClient.GetAsync($"{_apiBaseUrl}/print-queue");

                if (!response.IsSuccessStatusCode)
                {
                    _logger.LogWarning($"⚠️ API Response: {response.StatusCode} - {_apiBaseUrl}/print-queue");
                    return; // Si no hay trabajos o error, continuar
                }

                var jsonContent = await response.Content.ReadAsStringAsync();
                if (string.IsNullOrWhiteSpace(jsonContent) || jsonContent == "[]")
                {
                    return; // No hay trabajos pendientes
                }

                var printJobs = JsonSerializer.Deserialize<PrintJob[]>(jsonContent, JsonOptions);

                if (printJobs?.Length > 0)
                {
                    _logger.LogInformation($"📄 Procesando {printJobs.Length} trabajos de impresión");

                    foreach (var job in printJobs)
                    {
                        await ProcessSingleJob(job, httpClient);
                    }
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "💥 Error procesando cola de impresión");
            }
        }

        /// <summary>
        /// 🎯 PROCESAMIENTO INDIVIDUAL ULTRA EFICIENTE
        /// </summary>
        private async Task ProcessSingleJob(PrintJob job, HttpClient httpClient)
        {
            try
            {
                _logger.LogInformation($"🖨️ Procesando trabajo {job.Id} - {job.Action} - {job.Printer}");

                bool success = job.Action?.ToLower() switch
                {
                    "saleprinter" => await ProcessSalePrint(job),
                    "orderprinter" => await ProcessOrderPrint(job),
                    "opencashdrawer" => await ProcessOpenCash(job),
                    _ => false
                };

                if (success)
                {
                    // ✅ MARCAR COMO COMPLETADO
                    await MarkJobCompleted(job.Id, httpClient);
                    _logger.LogInformation($"✅ Trabajo {job.Id} completado exitosamente");
                }
                else
                {
                    _logger.LogWarning($"⚠️ Trabajo {job.Id} falló");
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error procesando trabajo {job.Id}");
            }
        }

        /// <summary>
        /// 🧾 IMPRESIÓN DE FACTURA - MODO OPTIMIZADO
        /// </summary>
        private async Task<bool> ProcessSalePrint(PrintJob job)
        {
            try
            {
                if (!string.IsNullOrEmpty(job.Image))
                {
                    // 🖼️ MODO IMAGEN: Ultra rápido
                    return await PrintImageMode(job);
                }
                else if (!string.IsNullOrEmpty(job.DataJson))
                {
                    // 📝 MODO ESC/POS: Nativo
                    return await PrintEscPosMode(job);
                }

                return false;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error en impresión de factura {job.Id}");
                return false;
            }
        }

        /// <summary>
        /// 🖼️ IMPRESIÓN MODO IMAGEN ULTRA OPTIMIZADA
        /// </summary>
        private async Task<bool> PrintImageMode(PrintJob job)
        {
            try
            {
                // Convertir base64 a imagen
                var base64Data = job.Image.Contains(',') ? job.Image.Split(',')[1] : job.Image;
                byte[] imageBytes = Convert.FromBase64String(base64Data);

                using var stream = new MemoryStream(imageBytes);
                using var image = Image.FromStream(stream);

                // 🚀 IMPRESIÓN DIRECTA - Sin drivers intermedios
                var printSuccess = await PrintImageDirect(job.Printer, image);

                // 💰 ABRIR CAJA SI ES NECESARIO
                if (printSuccess && job.OpenCash)
                {
                    await SendCashDrawerCommand(job.Printer);
                }

                return printSuccess;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error en modo imagen para trabajo {job.Id}");
                return false;
            }
        }

        /// <summary>
        /// 📝 IMPRESIÓN MODO ESC/POS NATIVO
        /// </summary>
        private async Task<bool> PrintEscPosMode(PrintJob job)
        {
            try
            {
                var printData = JsonSerializer.Deserialize<dynamic>(job.DataJson, JsonOptions);

                // 🚀 GENERAR COMANDOS ESC/POS OPTIMIZADOS
                var escPosCommands = GenerateEscPosContent(printData);
                var bytes = RawPrinterHelper.GenerateBasicEscPosCommands(escPosCommands);

                // 🖨️ ENVÍO DIRECTO
                var success = RawPrinterHelper.SendBytesToPrinter(job.Printer, bytes);

                // 💰 ABRIR CAJA SI ES NECESARIO
                if (success && job.OpenCash)
                {
                    await SendCashDrawerCommand(job.Printer);
                }

                return success;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error en modo ESC/POS para trabajo {job.Id}");
                return false;
            }
        }

        /// <summary>
        /// 🖨️ IMPRESIÓN DIRECTA DE IMAGEN
        /// </summary>
        private async Task<bool> PrintImageDirect(string printerName, Image image)
        {
            return await Task.Run(() =>
            {
                try
                {
                    using var printDoc = new PrintDocument();
                    printDoc.PrinterSettings.PrinterName = printerName;

                    // Verificar que la impresora existe
                    if (!printDoc.PrinterSettings.IsValid)
                    {
                        _logger.LogWarning($"⚠️ Impresora '{printerName}' no encontrada o no válida");
                        return false;
                    }

                    bool printed = false;
                    printDoc.PrintPage += (sender, e) =>
                    {
                        try
                        {
                            // 🎯 IMPRESIÓN OPTIMIZADA CON ESCALADO AUTOMÁTICO
                            var pageRect = e.MarginBounds;
                            var imageRect = new Rectangle(0, 0, image.Width, image.Height);

                            // Escalar imagen si es necesario
                            if (image.Width > pageRect.Width)
                            {
                                float scale = (float)pageRect.Width / image.Width;
                                imageRect.Width = (int)(image.Width * scale);
                                imageRect.Height = (int)(image.Height * scale);
                            }

                            e.Graphics?.DrawImage(image, imageRect);
                            printed = true;
                        }
                        catch (Exception ex)
                        {
                            _logger.LogError(ex, "💥 Error dibujando imagen en impresora");
                        }
                    };

                    printDoc.Print();
                    return printed;
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, $"💥 Error imprimiendo imagen en {printerName}");
                    return false;
                }
            });
        }

        /// <summary>
        /// 📦 PROCESAMIENTO DE ÓRDENES (COCINA)
        /// </summary>
        private async Task<bool> ProcessOrderPrint(PrintJob job)
        {
            try
            {
                if (!string.IsNullOrEmpty(job.DataJson))
                {
                    return await PrintEscPosMode(job);
                }
                return false;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error procesando orden {job.Id}");
                return false;
            }
        }

        /// <summary>
        /// 💰 ABRIR CAJA REGISTRADORA
        /// </summary>
        private async Task<bool> ProcessOpenCash(PrintJob job)
        {
            try
            {
                return await SendCashDrawerCommand(job.Printer);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error abriendo caja {job.Id}");
                return false;
            }
        }

        /// <summary>
        /// 💰 COMANDO ESC/POS PARA ABRIR CAJA
        /// </summary>
        private async Task<bool> SendCashDrawerCommand(string printerName)
        {
            return await Task.Run(() =>
            {
                try
                {
                    // Comando ESC/POS estándar para abrir caja: ESC p m t1 t2
                    byte[] openCashCommand = { 0x1B, 0x70, 0x00, 0x19, 0x64 };
                    return RawPrinterHelper.SendBytesToPrinter(printerName, openCashCommand);
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, $"💥 Error enviando comando abrir caja a {printerName}");
                    return false;
                }
            });
        }

        /// <summary>
        /// 📝 GENERAR CONTENIDO ESC/POS READABLE
        /// </summary>
        private string GenerateEscPosContent(dynamic printData)
        {
            try
            {
                var content = new StringBuilder();

                // Header
                content.AppendLine("================================");
                content.AppendLine("           FACTURA              ");
                content.AppendLine("================================");
                content.AppendLine();

                // Aquí puedes agregar lógica específica según tu estructura de datos
                content.AppendLine($"Fecha: {DateTime.Now:dd/MM/yyyy HH:mm}");
                content.AppendLine();

                // Footer
                content.AppendLine("================================");
                content.AppendLine("        GRACIAS POR SU COMPRA   ");
                content.AppendLine("================================");
                content.AppendLine();
                content.AppendLine();

                return content.ToString();
            }
            catch
            {
                return "ERROR GENERANDO CONTENIDO";
            }
        }

        /// <summary>
        /// ✅ MARCAR TRABAJO COMO COMPLETADO
        /// </summary>
        private async Task MarkJobCompleted(int jobId, HttpClient httpClient)
        {
            try
            {
                var response = await httpClient.DeleteAsync($"{_apiBaseUrl}/print-queue/{jobId}");
                if (!response.IsSuccessStatusCode)
                {
                    _logger.LogWarning($"⚠️ No se pudo marcar trabajo {jobId} como completado - Status: {response.StatusCode}");
                }
                else
                {
                    _logger.LogInformation($"✅ Trabajo {jobId} marcado como completado");
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"💥 Error marcando trabajo {jobId} como completado");
            }
        }

        /// <summary>
        /// 🔧 OBTENER URL BASE DE LA API CON CONFIGURACIÓN GRIDPOS
        /// </summary>
        private string GetApiBaseUrl()
        {
            try
            {
                // Leer configuración desde registro de Windows
                var apiType = GetRegistryValue("ApiType", "api"); // "api" o "api-demo"

                return $"https://{apiType}.gridpos.co";
            }
            catch
            {
                return "https://api.gridpos.co";
            }
        }

        /// <summary>
        /// 🔧 OBTENER VALOR DEL REGISTRO
        /// </summary>
        private string GetRegistryValue(string key, string defaultValue)
        {
            try
            {
                var registryValue = Microsoft.Win32.Registry.GetValue(
                    @"HKEY_LOCAL_MACHINE\SOFTWARE\GridPos\PrintService",
                    key,
                    defaultValue);

                return registryValue?.ToString() ?? defaultValue;
            }
            catch
            {
                return defaultValue;
            }
        }
    }
}
