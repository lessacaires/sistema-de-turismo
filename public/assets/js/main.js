// Funções JavaScript para o Sistema de Turismo

// Função para inicializar tooltips do Bootstrap
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Função para inicializar popovers do Bootstrap
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Função para formatar valores monetários
function formatMoney(value) {
    return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
}

// Função para formatar datas
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

// Função para formatar data e hora
function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
}

// Função para confirmar exclusão
function confirmDelete(message = 'Tem certeza que deseja excluir este item?') {
    return confirm(message);
}

// Função para imprimir um elemento específico
function printElement(elementId) {
    const element = document.getElementById(elementId);
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = element.innerHTML;
    window.print();
    document.body.innerHTML = originalContents;
    
    // Reinicializar tooltips e popovers após restaurar o conteúdo
    initTooltips();
    initPopovers();
}

// Função para atualizar o total de um pedido
function updateOrderTotal() {
    let total = 0;
    
    // Seleciona todos os elementos com a classe 'item-total'
    document.querySelectorAll('.item-total').forEach(function(element) {
        // Remove o símbolo de moeda e converte para número
        const value = parseFloat(element.textContent.replace('R$ ', '').replace(',', '.'));
        if (!isNaN(value)) {
            total += value;
        }
    });
    
    // Atualiza o elemento com o total
    const totalElement = document.getElementById('order-total');
    if (totalElement) {
        totalElement.textContent = formatMoney(total);
    }
    
    return total;
}

// Função para adicionar um item ao pedido
function addOrderItem(productId, productName, price) {
    const orderItemsTable = document.getElementById('order-items');
    const tbody = orderItemsTable.querySelector('tbody');
    
    // Verifica se o produto já está no pedido
    const existingRow = tbody.querySelector(`tr[data-product-id="${productId}"]`);
    
    if (existingRow) {
        // Se o produto já existe, incrementa a quantidade
        const quantityInput = existingRow.querySelector('.item-quantity');
        const currentQuantity = parseInt(quantityInput.value);
        quantityInput.value = currentQuantity + 1;
        
        // Atualiza o total do item
        const totalElement = existingRow.querySelector('.item-total');
        const newTotal = (currentQuantity + 1) * price;
        totalElement.textContent = formatMoney(newTotal);
    } else {
        // Se o produto não existe, adiciona uma nova linha
        const newRow = document.createElement('tr');
        newRow.setAttribute('data-product-id', productId);
        
        newRow.innerHTML = `
            <td>${productName}</td>
            <td>
                <input type="number" class="form-control form-control-sm item-quantity" value="1" min="1" style="width: 70px" onchange="updateItemTotal(this)">
            </td>
            <td class="item-price">${formatMoney(price)}</td>
            <td class="item-total">${formatMoney(price)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeOrderItem(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
            <input type="hidden" name="product_id[]" value="${productId}">
            <input type="hidden" name="product_price[]" value="${price}">
        `;
        
        tbody.appendChild(newRow);
    }
    
    // Atualiza o total do pedido
    updateOrderTotal();
}

// Função para atualizar o total de um item quando a quantidade muda
function updateItemTotal(quantityInput) {
    const row = quantityInput.closest('tr');
    const priceElement = row.querySelector('.item-price');
    const totalElement = row.querySelector('.item-total');
    
    // Obtém o preço unitário (remove o símbolo de moeda e converte para número)
    const price = parseFloat(priceElement.textContent.replace('R$ ', '').replace(',', '.'));
    
    // Calcula o novo total
    const quantity = parseInt(quantityInput.value);
    const newTotal = price * quantity;
    
    // Atualiza o total do item
    totalElement.textContent = formatMoney(newTotal);
    
    // Atualiza o total do pedido
    updateOrderTotal();
}

// Função para remover um item do pedido
function removeOrderItem(button) {
    if (confirm('Tem certeza que deseja remover este item?')) {
        const row = button.closest('tr');
        row.remove();
        
        // Atualiza o total do pedido
        updateOrderTotal();
    }
}

// Inicializa tooltips e popovers quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    initPopovers();
});
