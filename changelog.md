# Changelog - MKP Multisite WooCommerce Integrator

## [1.0.1] - 2025-06-30

### 🐛 Correções de Bugs
- **CRÍTICO**: Corrigido erro de compatibilidade com WooCommerce Subscriptions 7.6.0+
  - Função `wcs_get_subscriptions()` agora aceita argumentos obrigatórios na nova versão
  - Implementado wrapper seguro `get_subscriptions_safe()` para compatibilidade com múltiplas versões
  - Adicionado tratamento de exceções `ArgumentCountError`

### ✨ Novas Funcionalidades
- **Verificador de Compatibilidade**: Nova classe `MKP_Compatibility_Checker`
  - Verificação automática de dependências e versões
  - Detecção de problemas de compatibilidade em tempo real
  - Relatório detalhado de compatibilidade
- **Avisos Administrativos**: Sistema de notificações no painel admin
  - Avisos específicos para problemas de compatibilidade
  - Interface visual para detalhes de compatibilidade
  - Informações do sistema e diagnósticos

### 🔧 Melhorias
- **Tratamento de Erros**: Implementado logging robusto para todos os métodos
- **Compatibilidade Retroativa**: Mantida compatibilidade com versões anteriores do WCS
- **Documentação**: Comentários detalhados e documentação inline
- **Debug**: Integração com WordPress Site Health para informações de debug

### 🛠️ Alterações Técnicas
- Atualizado método `get_subscription_stats()` (linha 147 era o problema original)
- Implementada reflexão PHP para detectar assinatura de funções
- Adicionados hooks para verificação após atualizações de plugins
- Melhorado sistema de logs com contexto específico

### 📋 Requisitos Atualizados
- **WooCommerce Subscriptions**: 7.0.0+ (recomendado 7.6.0+)
- **PHP**: 8.0+ (mantido)
- **WordPress**: 6.0+ (mantido)

### 🔍 Detalhes da Correção

#### Problema Original:
```php
// ERRO - Linha 147 class-subscription-manager.php
$subscriptions = wcs_get_subscriptions(); // ❌ Sem argumentos
