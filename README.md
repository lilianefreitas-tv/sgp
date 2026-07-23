# 🚀 SGP: Sistema de Gestão de Projetos de Software

O **SGP** é uma plataforma integrada para gerenciamento do ciclo de vida de projetos de software, desenvolvida para centralizar requisitos, tarefas, documentação, testes, reuniões, mudanças e entregas em um único ambiente.

O projeto foi idealizado para reduzir a fragmentação causada pelo uso de diversas ferramentas independentes, promovendo organização, rastreabilidade, produtividade e qualidade durante todo o desenvolvimento de software.

> **Status do projeto:** Em desenvolvimento

---

# 🎯 Objetivo

Disponibilizar uma plataforma única para apoiar todas as etapas do desenvolvimento de software, desde a concepção da ideia até a entrega final do produto.

O SGP busca adaptar-se tanto a pequenos projetos quanto a iniciativas corporativas que exijam governança, rastreabilidade e produção de artefatos formais. 

---

# 💡 Problema

Equipes de desenvolvimento normalmente utilizam diversas ferramentas para controlar:

- Requisitos
- Tarefas
- Protótipos
- Reuniões
- Testes
- Documentação
- Versionamento
- Entregas

Essa fragmentação dificulta a gestão dos projetos, gera retrabalho, reduz a rastreabilidade e aumenta o esforço necessário para manter a documentação atualizada.

---

# ✅ Solução Proposta

O SGP reúne todas essas atividades em uma única plataforma, permitindo que a equipe acompanhe todo o ciclo de vida do projeto em um ambiente integrado.

A proposta é oferecer uma solução escalável:

- Simples para pequenos projetos.
- Completa para projetos institucionais.
- Robusta para ambientes que exigem auditoria e governança.



---

## 🏗 Arquitetura

O SGP está sendo desenvolvido como uma aplicação web utilizando a arquitetura MVC, adotada pelo Laravel:

- **Model:** representação e manipulação dos dados;
- **View:** apresentação das páginas por meio do Blade;
- **Controller:** tratamento das requisições e aplicação das regras do sistema.

O PostgreSQL será utilizado para persistência dos dados, enquanto o Eloquent ORM realizará a comunicação entre a aplicação e o banco de dados.

--- 
## 🛠 Tecnologias e ferramentas

### Backend

- PHP 8.2 ou superior
- Laravel
- PostgreSQL
- Eloquent ORM
- Blade Template Engine
- PHPWord para geração de arquivos DOCX
- Dompdf para geração de arquivos PDF

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Gerenciamento e construção

- Composer
- Node.js e npm
- Vite

### Versionamento

- Git
- GitHub

### Ambiente de desenvolvimento

- Windows
- Visual Studio Code
- PostgreSQL
- Laravel Artisan

---

## 📦 Escopo do MVP

A primeira versão do SGP será concentrada nas funcionalidades essenciais para criação e acompanhamento de projetos de software:

- Autenticação de usuários
- Gestão de usuários e perfis de acesso
- Cadastro de projetos
- Gestão de participantes do projeto
- Gestão de requisitos funcionais e não funcionais
- Registro de regras de negócio
- Controle de tarefas
- Histórico básico de alterações
- Geração e versionamento de documentos em DOCX e PDF
- Painel de acompanhamento do projeto

---

## 🔭 Módulos Previstos

- Gestão de reuniões
- Gestão de testes e evidências
- Gestão documental
- Gestão de mudanças
- Gestão de riscos
- Gestão da qualidade
- Matriz de rastreabilidade
- Geração automática de artefatos
- Integração com Inteligência Artificial

---

# 📄 Artefatos Gerados

O SGP será capaz de gerar automaticamente documentos como:

- Documento de Visão
- Termo de Abertura
- Especificação Funcional
- Regras de Negócio
- Casos de Uso
- Casos de Teste
- Matriz de Rastreabilidade
- Plano de Testes
- Termo de Aceite
- Lições Aprendidas

---

# 📊 Níveis de Utilização

O sistema poderá ser utilizado conforme a complexidade do projeto.

## Simplificado

- Documento de Visão
- Requisitos
- Tarefas

## Intermediário

- Documento de Visão
- Requisitos
- Protótipos
- Tarefas
- Reuniões
- Testes
- Termo de Aceite

## Completo

- Gestão integral do projeto
- Governança
- Qualidade
- Rastreabilidade
- Documentação automática



---

# ⭐ Diferenciais

- Plataforma única para Engenharia de Software.
- Documentação gerada automaticamente.
- Rastreabilidade entre requisitos, tarefas, testes e entregas.
- Suporte a metodologias ágeis.
- Flexibilidade conforme o porte do projeto.
- Preparado para integração com Inteligência Artificial.
- Aderência às boas práticas de Engenharia de Software e MPS.BR.



---

# 🤖 Visão de Futuro

Entre as evoluções previstas estão:

- Assistente de IA para Engenharia de Requisitos.
- Geração automática de documentação.
- Sugestão automática de Casos de Teste.
- Identificação de riscos.
- Apoio à gestão de projetos.
- Integração com modelos de linguagem (LLMs).


---

# 👥 Público-Alvo

- Analistas de Requisitos
- Gerentes de Projetos
- Desenvolvedores
- Testadores
- Product Owners
- Consultorias de Software
- Órgãos Públicos
- Empresas de Tecnologia


---

# 👩‍💻 Desenvolvedora

**Liliane de Freitas Terra Vieira**

Analista de Requisitos • Desenvolvedora • Técnica em Tecnologia da Informação

---

## 📌 Status do Projeto

🚧 **Em desenvolvimento**

O projeto encontra-se na fase inicial de implementação, com o ambiente de desenvolvimento configurado, aplicação Laravel criada, banco de dados PostgreSQL integrado e repositório GitHub estruturado.

A etapa atual está concentrada na construção da base do sistema, incluindo autenticação, controle de usuários, perfis de acesso e estrutura inicial dos projetos.
