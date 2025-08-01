using System;
using System.Runtime.InteropServices;
using System.Text;

namespace GridPosPrintService
{
    /// <summary>
    /// 🚀 HELPER NATIVO PARA ENVÍO DIRECTO A IMPRESORA
    /// Usa WinAPI para máximo rendimiento sin intermediarios
    /// </summary>
    public static class RawPrinterHelper
    {
        #region WinAPI Declarations
        [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

        [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool ClosePrinter(IntPtr hPrinter);

        [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

        [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool EndDocPrinter(IntPtr hPrinter);

        [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool StartPagePrinter(IntPtr hPrinter);

        [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool EndPagePrinter(IntPtr hPrinter);

        [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
        public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, Int32 dwCount, out Int32 dwWritten);

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
        public class DOCINFOA
        {
            [MarshalAs(UnmanagedType.LPStr)]
            public string pDocName;
            [MarshalAs(UnmanagedType.LPStr)]
            public string pOutputFile;
            [MarshalAs(UnmanagedType.LPStr)]
            public string pDataType;
        }
        #endregion

        /// <summary>
        /// 🚀 ENVÍO ULTRA RÁPIDO DE DATOS BINARIOS A IMPRESORA
        /// </summary>
        public static bool SendBytesToPrinter(string printerName, byte[] bytes)
        {
            IntPtr hPrinter = new IntPtr(0);
            DOCINFOA di = new DOCINFOA();
            bool success = false;

            try
            {
                di.pDocName = "GridPos Print Job";
                di.pDataType = "RAW";

                if (OpenPrinter(printerName.Normalize(), out hPrinter, IntPtr.Zero))
                {
                    if (StartDocPrinter(hPrinter, 1, di))
                    {
                        if (StartPagePrinter(hPrinter))
                        {
                            IntPtr pBytes = Marshal.AllocHGlobal(bytes.Length);
                            Marshal.Copy(bytes, 0, pBytes, bytes.Length);

                            success = WritePrinter(hPrinter, pBytes, bytes.Length, out int bytesWritten);

                            Marshal.FreeHGlobal(pBytes);
                            EndPagePrinter(hPrinter);
                        }
                        EndDocPrinter(hPrinter);
                    }
                    ClosePrinter(hPrinter);
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine($"Error en envío a impresora: {ex.Message}");
            }

            return success;
        }

        /// <summary>
        /// 📝 ENVÍO DE TEXTO CON CODIFICACIÓN ESPECÍFICA
        /// </summary>
        public static bool SendStringToPrinter(string printerName, string text)
        {
            byte[] bytes = Encoding.UTF8.GetBytes(text);
            return SendBytesToPrinter(printerName, bytes);
        }

        /// <summary>
        /// 🧾 GENERADOR DE COMANDOS ESC/POS BÁSICOS
        /// </summary>
        public static byte[] GenerateBasicEscPosCommands(string content)
        {
            var commands = new System.Collections.Generic.List<byte>();

            // Inicializar impresora
            commands.AddRange(new byte[] { 0x1B, 0x40 }); // ESC @

            // Configurar codificación UTF-8
            commands.AddRange(new byte[] { 0x1B, 0x74, 0x12 }); // ESC t 18

            // Agregar contenido
            commands.AddRange(Encoding.UTF8.GetBytes(content));

            // Salto de línea y corte
            commands.AddRange(new byte[] { 0x0A, 0x0A }); // LF LF
            commands.AddRange(new byte[] { 0x1D, 0x56, 0x42, 0x00 }); // GS V B 0 (corte completo)

            return commands.ToArray();
        }
    }
}
