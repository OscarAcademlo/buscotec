import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:buscotec_flutter/theme/app_theme.dart';
import 'package:buscotec_flutter/services/api_service.dart';

class CasosScreen extends StatefulWidget {
  const CasosScreen({super.key});

  @override
  State<CasosScreen> createState() => _CasosScreenState();
}

class _CasosScreenState extends State<CasosScreen> {
  bool _loading = true;
  List<dynamic> _casos = [];
  String _totalPendiente = "0.00";
  String _error = '';

  @override
  void initState() {
    super.initState();
    _cargarCasos();
  }

  Future<void> _cargarCasos() async {
    try {
      final uid = ApiService.currentUserId ?? 0;
      final role = ApiService.currentRole;

      final resp = await http.get(
        Uri.parse('https://buscotec.click/backend/listar_casos_app.php'
            '?id=$uid&role=$role'),
        headers: {
          'X-User-Id': uid.toString(),
          'X-User-Role': role,
        },
      ).timeout(const Duration(seconds: 10));

      if (resp.statusCode == 200) {
        final data = jsonDecode(resp.body);
        if (data['ok'] == true) {
          if (mounted) {
            setState(() {
              _casos = data['casos'] ?? [];
              _totalPendiente = data['total']?.toString() ?? "0.00";
              _loading = false;
            });
          }
        } else {
          if (mounted) setState(() { _error = data['error'] ?? 'Error desconocido'; _loading = false; });
        }
      } else {
        if (mounted) setState(() { _error = 'Error del servidor: ${resp.statusCode}'; _loading = false; });
      }
    } catch (e) {
      if (mounted) setState(() { _error = 'Error de conexión: $e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (ApiService.currentRole != 'profesional' && !ApiService.hasDoubleRole) {
      return Scaffold(
        appBar: AppBar(title: const Text('Estado de Cuenta')),
        body: const Center(child: Text('Solo disponible para profesionales.')),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      appBar: AppBar(
        title: Text('Estado de Cuenta', style: GoogleFonts.inter(fontWeight: FontWeight.bold)),
        backgroundColor: AppTheme.buscotecBlue,
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error.isNotEmpty
              ? Center(child: Text(_error, style: const TextStyle(color: Colors.red)))
              : RefreshIndicator(
                  onRefresh: _cargarCasos,
                  child: Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(20),
                        color: Colors.white,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Total adeudado:', style: GoogleFonts.inter(fontSize: 16)),
                            Text('\$$_totalPendiente', style: GoogleFonts.inter(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.red)),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      Expanded(
                        child: _casos.isEmpty
                            ? ListView(
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.all(20),
                                    child: Container(
                                      padding: const EdgeInsets.all(16),
                                      decoration: BoxDecoration(
                                        color: AppTheme.buscotecBlue.withOpacity(0.1),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: const Text('No hay casos aceptados para mostrar.', textAlign: TextAlign.center),
                                    ),
                                  ),
                                ],
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.all(16),
                                itemCount: _casos.length,
                                itemBuilder: (context, index) {
                                  final caso = _casos[index];
                                  final pagado = (caso['pagado'] ?? 0).toString() == '1';
                                  final importe = double.tryParse(caso['importe']?.toString() ?? '0') ?? 0.0;

                                  return Card(
                                    margin: const EdgeInsets.only(bottom: 12),
                                    elevation: 2,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    color: pagado ? Colors.white.withOpacity(0.7) : Colors.white,
                                    child: Padding(
                                      padding: const EdgeInsets.all(16),
                                      child: Stack(
                                        children: [
                                          Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                children: [
                                                  Expanded(
                                                    child: Column(
                                                      crossAxisAlignment: CrossAxisAlignment.start,
                                                      children: [
                                                        Text('Fecha: ${caso['fecha']} — Hora: ${caso['hora']}', style: GoogleFonts.inter(fontWeight: FontWeight.bold, fontSize: 13)),
                                                        Text('Caso #${caso['id']}', style: GoogleFonts.inter(color: Colors.grey, fontSize: 12)),
                                                      ],
                                                    ),
                                                  ),
                                                  Text(
                                                    '+\$${importe.toStringAsFixed(2)}',
                                                    style: GoogleFonts.inter(
                                                      fontWeight: FontWeight.bold,
                                                      fontSize: 15,
                                                      color: pagado ? Colors.grey : Colors.black,
                                                      decoration: pagado ? TextDecoration.lineThrough : null,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              const Divider(height: 24),
                                              Text('Cliente: ${caso['nombre']} ${caso['apellido']}', style: GoogleFonts.inter(fontSize: 13)),
                                              const SizedBox(height: 4),
                                              Text('WhatsApp: ${caso['whatsapp']}', style: GoogleFonts.inter(fontSize: 13)),
                                            ],
                                          ),
                                          if (pagado)
                                            Positioned(
                                              top: 0,
                                              right: 50,
                                              child: Transform.rotate(
                                                angle: -0.15,
                                                child: Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                                  decoration: BoxDecoration(
                                                    color: Colors.green,
                                                    borderRadius: BorderRadius.circular(6),
                                                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.2), blurRadius: 4, offset: const Offset(0, 2))],
                                                  ),
                                                  child: Text('PAGADO', style: GoogleFonts.inter(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                      ),
                    ],
                  ),
                ),
    );
  }
}
