# ADR-007 — RBAC Deployment Contract

## Context
O CreateTenantService depende de Permissions existentes no catálogo global.

## Decision
Todo deploy de produção deve obedecer:

1. php artisan migrate --force
2. php artisan db:seed --class=PermissionCatalogSeeder --force
3. somente então liberar aplicação/tráfego

## Failure Rule
Se o PermissionCatalogSeeder falhar:
- deploy falha;
- release não deve receber tráfego;
- nenhum fallback cria Permissions dinamicamente.

## Catalog Ownership
PermissionSlug é a fonte canônica.
PermissionCatalogSeeder sincroniza o banco.
Permissions legadas não são apagadas.

## Rollback
Rollback de código não deve apagar Permissions.
Permissions adicionadas por versões mais novas podem permanecer no banco.
Versões antigas devem ignorá-las.

## Zero Downtime
O catálogo deve ser sincronizado antes do tráfego da nova versão.
Novas Permissions são aditivas.

## Current Operational Gap
Não existe CI/CD versionado no repositório.
O contrato deve ser respeitado pela automação externa ou futura pipeline.

## Non-Goals
- Permission CRUD
- PermissionResolver
- cache
- Platform Admin
- ABAC
- ACL
- novas capabilities
