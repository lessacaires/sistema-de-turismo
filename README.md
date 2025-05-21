# Sistema de Gestão para Turismo

Um sistema completo para gestão de negócios de turismo, incluindo recepção, vendas de passeios, restaurante/bar, PDV, controle financeiro, estoque, funcionários e compras.

## Funcionalidades

O sistema inclui os seguintes módulos:

### 1. Módulo de Atendimento Receptivo
- Cadastro e gestão de clientes
- Visualização detalhada de clientes

### 2. Módulo de Vendas de Passeios
- Cadastro e gestão de passeios
- Agendamento de passeios
- Venda de passeios

### 3. Módulo de Restaurante e Bar
- Gerenciamento de mesas
- Sistema de comandas
- Edição de comandas

### 4. PDV (Ponto de Venda)
- Interface para vendas
- Carrinho de compras
- Finalização de vendas

### 5. Módulo de Controle Financeiro
- Dashboard financeiro
- Registro de transações
- Relatórios financeiros

### 6. Módulo de Controle de Estoque
- Gestão de produtos
- Movimentação de estoque
- Histórico de movimentações

### 7. Módulo de Controle de Funcionários
- Cadastro de funcionários
- Gestão de acesso ao sistema
- Controle de permissões

### 8. Módulo de Controle de Compras
- Cadastro de fornecedores
- Registro de compras
- Recebimento de mercadorias

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache, Nginx)
- Navegador web moderno

## Instalação

1. Clone o repositório:
```bash
git clone https://github.com/seu-usuario/sistema-turismo.git
```

2. Configure o banco de dados:
   - Crie um banco de dados MySQL
   - Importe o arquivo `database/schema.sql`
   - Configure as credenciais no arquivo `config/database.php`

3. Configure o servidor web:
   - Aponte o document root para a pasta `public/`
   - Certifique-se de que o mod_rewrite está habilitado (se estiver usando Apache)

4. Acesse o sistema:
   - URL: `http://seu-servidor/`
   - Usuário padrão: `admin`
   - Senha padrão: `admin123`

## Estrutura do Projeto

```
sistema-turismo/
├── config/             # Arquivos de configuração
├── database/           # Scripts de banco de dados
├── functions/          # Funções utilitárias
├── public/             # Arquivos públicos (document root)
│   ├── admin/          # Módulos administrativos
│   ├── receptive/      # Módulo de recepção
│   ├── restaurant/     # Módulo de restaurante
│   ├── tours/          # Módulo de passeios
│   ├── pos/            # Módulo de PDV
│   └── index.php       # Página inicial
└── template/           # Templates e layouts
```

## Licença

Este projeto está licenciado sob a licença MIT - veja o arquivo LICENSE para detalhes.

## Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues e enviar pull requests.

## Autor

Desenvolvido por [Seu Nome]
