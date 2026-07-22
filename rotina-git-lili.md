
# 🔁 Rotina Git – Projeto RotaMP (Lili)

Este arquivo contém o passo a passo para manter o repositório sincronizado entre o PC do trabalho e o PC de casa, com segurança e controle total.

---

## 🚀 1. Ao iniciar o trabalho (em qualquer máquina)

### 👉 Entrar na pasta do projeto:
```bash
cd C:\xampp\htdocs\rotamp
```

### 👉 Puxar as últimas alterações do GitHub:
```bash
git pull origin main
```

🔹 *Isso garante que você tenha a versão mais atualizada antes de começar qualquer coisa.*

---

## ✍️ 2. Após editar, renomear ou adicionar arquivos

### 👉 Verificar o que foi alterado:
```bash
git status
```

### 👉 Adicionar tudo para commit:
```bash
git add .
```

### 👉 Criar o commit com uma mensagem clara:
```bash
git commit -m "Descreve aqui o que foi feito"
```

---

## ☁️ 3. Enviar as alterações para o GitHub

### 👉 Enviar (fazer o push):
```bash
git push origin main
```

---

## 🔁 4. Ao mudar de máquina

Quando for usar outro computador (ex: sair do trabalho e ir pra casa ou vice-versa):

### 👉 Primeiro passo SEMPRE é:
```bash
git pull origin main
```

🔹 *Assim você garante que nenhuma alteração fique pra trás e evita conflitos.*

---

## 🛠️ Comandos rápidos de referência

|                         Ação                                       |        Comando                    |
|--------------------------------------------------------------------|-----------------------------------|
| Ver estado                                                         | `git status`                      |
| Adicionar tudo                                                     | `git add .`                       |
| Criar commit                                                       | `git commit -m "mensagem"`        |
| Enviar pro GitHub                                                  | `git push origin main`            |
| Puxar do GitHub                                                    | `git pull origin main`            |
| Ver conexão remota                                                 | `git remote -v`                   |
| Ver branch atual                                                   | `git branch`                      |
| O -u cria o vínculo entre sua branch local main e a main do GitHub.| `git push -u origin`              |
| Enviar pro GitHub                                                  | `git push`                        |

---

## 🔐 Configuração SSH (PC de Casa e Trabalho)

Ambos os ambientes já estão configurados para usar **chave SSH no GitHub**, garantindo acesso seguro e sem necessidade de digitar login/senha em cada push.

### 🔹 Caminho da chave SSH:

```
C:\Users\SEU_USUARIO\.ssh\id_github
```

### 🔹 Arquivo de configuração (`~/.ssh/config`):

```ssh
# GitHub
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_github
```

### 🔹 Testar se está funcionando:

```bash
ssh -T git@github.com
```

Se aparecer:

```
Hi lilianefreitas-tv! You've successfully authenticated...
```

➡️ Tudo está OK!

### 🔹 Endereço remoto no Git configurado:

```bash
git remote -v
```

Resultado esperado:

```
origin  git@github.com:lilianefreitas-tv/rotamp.git (fetch)
origin  git@github.com:lilianefreitas-tv/rotamp.git (push)
```

---

✅ *A partir de agora, o Git usa sua chave SSH para autenticar e sincronizar o projeto sem pedir senha.*

✨ Com esse guia, você nunca mais se perde no fluxo Git.  
E o melhor: você já está usando tudo como uma dev PRO. 👩‍💻💚
