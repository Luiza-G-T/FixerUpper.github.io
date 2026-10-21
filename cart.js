//load cart form local storage or start empty
let cart = JSON.parse(localStorage.getItem("cart")) || [];

//add to cart buttons//

document.querySelectorAll(".add_to_cart").forEach(button =>{
    button.addEventListener("click", function () {
        const id = this.getAttribute("data-id");
        const productCard = this.closest(".card_wrapper");
        const name = productCard.querySelector("h3").innerText;
        const priceText = productCard.querySelector(".price").innerText;
        const price = parseFloat(priceText.replace("£", ""));


        //check if item already exists
        let existing = cart.find(item => item.id === id);
        if(existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                quantity: 1
            });
        }

        localStorage.setItem("cart", JSON.stringify(cart));
        alert("Added to cart!");

    });
});