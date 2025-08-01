using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using System;
using System.ServiceProcess;

namespace GridPosPrintService
{
    /// <summary>
    /// 🚀 PROGRAMA PRINCIPAL - SERVICIO NATIVO DE WINDOWS
    /// Ultra optimizado para Windows 10/11
    /// </summary>
    public class Program
    {
        public static void Main(string[] args)
        {
            // 🚀 VERIFICAR SI SE EJECUTA COMO SERVICIO O APLICACIÓN CONSOLA
            if (Environment.UserInteractive)
            {
                // 🖥️ MODO CONSOLA - Para desarrollo y testing
                Console.WriteLine("🚀 GridPos Print Service - Modo Consola");
                Console.WriteLine("Presiona 'q' para salir\n");

                RunAsConsole(args);
            }
            else
            {
                // 🔧 MODO SERVICIO - Para producción
                RunAsService(args);
            }
        }

        /// <summary>
        /// 🖥️ EJECUTAR COMO APLICACIÓN CONSOLA (Desarrollo)
        /// </summary>
        private static void RunAsConsole(string[] args)
        {
            var host = CreateHostBuilder(args).Build();

            var cancellationTokenSource = new System.Threading.CancellationTokenSource();

            // Iniciar el servicio en background
            var serviceTask = host.RunAsync(cancellationTokenSource.Token);

            // Esperar input del usuario
            ConsoleKeyInfo keyInfo;
            do
            {
                keyInfo = Console.ReadKey(true);

                if (keyInfo.Key == ConsoleKey.S)
                {
                    Console.WriteLine("📊 Estado del servicio: Activo");
                }
                else if (keyInfo.Key == ConsoleKey.H)
                {
                    ShowHelp();
                }

            } while (keyInfo.Key != ConsoleKey.Q);

            Console.WriteLine("\n🛑 Deteniendo servicio...");
            cancellationTokenSource.Cancel();

            try
            {
                serviceTask.Wait(5000); // Esperar máximo 5 segundos
            }
            catch (AggregateException ex)
            {
                Console.WriteLine($"Error al detener: {ex.Message}");
            }

            Console.WriteLine("✅ Servicio detenido");
        }

        /// <summary>
        /// 🔧 EJECUTAR COMO SERVICIO DE WINDOWS (Producción)
        /// </summary>
        private static void RunAsService(string[] args)
        {
            CreateHostBuilder(args)
                .UseWindowsService()
                .Build()
                .Run();
        }

        /// <summary>
        /// 🏗️ CONSTRUCTOR DEL HOST OPTIMIZADO
        /// </summary>
        public static IHostBuilder CreateHostBuilder(string[] args) =>
            Host.CreateDefaultBuilder(args)
                .UseWindowsService(options =>
                {
                    options.ServiceName = "GridPosPrintService";
                })
                .ConfigureServices((hostContext, services) =>
                {
                    // 🚀 REGISTRAR EL SERVICIO PRINCIPAL
                    services.AddHostedService<GridPosPrintWorker>();

                    // 🔧 CONFIGURAR LOGGING OPTIMIZADO
                    services.AddLogging(builder =>
                    {
                        builder.ClearProviders();
                        builder.AddConsole();
                        builder.AddEventLog(settings =>
                        {
                            settings.SourceName = "GridPosPrintService";
                        });
                    });

                    // 📡 CONFIGURAR HTTP CLIENT OPTIMIZADO
                    services.AddHttpClient("GridPosApi", client =>
                    {
                        client.Timeout = TimeSpan.FromSeconds(5);
                        client.DefaultRequestHeaders.Add("User-Agent", "GridPosPrintService/1.0");
                    });
                });

        /// <summary>
        /// ❓ MOSTRAR AYUDA EN MODO CONSOLA
        /// </summary>
        private static void ShowHelp()
        {
            Console.WriteLine("\n🔧 GridPos Print Service - Comandos:");
            Console.WriteLine("  S - Mostrar estado del servicio");
            Console.WriteLine("  H - Mostrar esta ayuda");
            Console.WriteLine("  Q - Salir del servicio");
            Console.WriteLine();
        }
    }

    /// <summary>
    /// 🔧 WORKER PRINCIPAL DEL SERVICIO
    /// </summary>
    public class GridPosPrintWorker : BackgroundService
    {
        private readonly ILogger<GridPosPrintWorker> _logger;
        private readonly IHttpClientFactory _httpClientFactory;
        private readonly GridPosPrintProcessor _processor;

        public GridPosPrintWorker(
            ILogger<GridPosPrintWorker> logger,
            IHttpClientFactory httpClientFactory)
        {
            _logger = logger;
            _httpClientFactory = httpClientFactory;
            _processor = new GridPosPrintProcessor(_httpClientFactory, _logger);
        }

        protected override async Task ExecuteAsync(CancellationToken stoppingToken)
        {
            _logger.LogInformation("🚀 GridPos Print Service iniciado");

            try
            {
                while (!stoppingToken.IsCancellationRequested)
                {
                    await _processor.ProcessPrintQueue();

                    // 🚀 PAUSA ULTRA OPTIMIZADA - Solo 2 segundos
                    await Task.Delay(2000, stoppingToken);
                }
            }
            catch (OperationCanceledException)
            {
                _logger.LogInformation("🛑 Servicio detenido por solicitud");
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "💥 Error crítico en el servicio");
            }
            finally
            {
                _logger.LogInformation("✅ GridPos Print Service finalizado");
            }
        }

        public override Task StartAsync(CancellationToken cancellationToken)
        {
            _logger.LogInformation("🔄 Iniciando GridPos Print Service...");
            return base.StartAsync(cancellationToken);
        }

        public override Task StopAsync(CancellationToken cancellationToken)
        {
            _logger.LogInformation("🔄 Deteniendo GridPos Print Service...");
            return base.StopAsync(cancellationToken);
        }
    }
}
