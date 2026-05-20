<?php
require_once __DIR__ . '/server/connection.php';
include __DIR__ . '/layouts/header.php';
?>

    <!-- Home -->
    <section id='home'>
        <div class="container">
            <h6>New Arrivals</h6>
            <h1><span>Best Prices</span><br>this Season</h1>
            <p>Kimmi offers the finest products at the most affordable prices.</p>
            <a href="shop.php"><button>Shop Now</button></a>
        </div>
    </section>

    <!-- BRANDS -->
    <section id="brands" class="container">
        <div class="row">
            <img class="img-fluid col-lg-3 col-md-6 col-sm-12" src="assets/imgs/brand1.jpeg" alt="Brand 1" />
            <img class="img-fluid col-lg-3 col-md-6 col-sm-12" src="assets/imgs/brand2.jpeg" alt="Brand 2" />
            <img class="img-fluid col-lg-3 col-md-6 col-sm-12" src="assets/imgs/brand3.jpeg" alt="Brand 3" />
            <img class="img-fluid col-lg-3 col-md-6 col-sm-12" src="assets/imgs/brand4.jpeg" alt="Brand 4" />
        </div>
    </section>

    <!-- New -->
    <section id="new" class="w-100">
        <div class="row p-0 m-0">
            <div class="one col-lg-4 col-md-12 col-sm-12 p-0">
                <img class="img-fluid" src="assets/imgs/1.jpeg" alt="" />
                <div class="details">
                    <h2>Awesome Quality</h2>
                    <a href="shop.php"><button class="text-uppercase">Shop Now</button></a>
                </div>
            </div>
            <div class="one col-lg-4 col-md-12 col-sm-12 p-0">
                <img class="img-fluid" src="assets/imgs/2.jpeg" alt="" />
                <div class="details">
                    <h2>Best Value</h2>
                    <a href="shop.php"><button class="text-uppercase">Shop Now</button></a>
                </div>
            </div>
            <div class="one col-lg-4 col-md-12 col-sm-12 p-0">
                <img class="img-fluid" src="assets/imgs/3.jpeg" alt="" />
                <div class="details">
                    <h2>Free Returns</h2>
                    <a href="shop.php"><button class="text-uppercase">Shop Now</button></a>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURED -->
    <section id="featured" class="my-5 pb-5">
        <div class="container text-center mt-5 py-5">
            <h3>Our Featured Products</h3>
            <hr class="mx-auto">
            <p>Here you can check out our featured products</p>
        </div>

        <div class="container-fluid px-4"><div class="row">
            <?php include __DIR__ . '/server/get_featured_products.php'; ?>
            <?php while ($row = $featured_products->fetch_assoc()): ?>
                <div class="product text-center col-lg-3 col-md-4 col-sm-6 col-12">
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <div class="product-img-wrap">
                            <img src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                                 alt="<?= e((string)$row['product_name']) ?>" />
                        </div>
                    </a>
                    <div class="star">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <h5 class="p-name"><?= e((string)$row['product_name']) ?></h5>
                    <h4 class="p-price">$<?= e(number_format((float)$row['product_price'], 2)) ?></h4>
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <span class="buy-btn">Buy Now</span>
                    </a>
                </div>
            <?php endwhile; ?>
        </div></div>
    </section>

    <!-- Banner -->
    <section id="banner" class="my-5 py-5">
        <div class="container">
            <h4>MID SEASON SALES</h4>
            <h1>Black Friday Deals<br> UP to 30% OFF</h1>
            <a href="shop.php"><button class="text-uppercase">shop now</button></a>
        </div>
    </section>

    <!-- Clothing -->
    <section id="featured-clothing" class="my-5 pb-5">
        <div class="container text-center mt-5 py-5">
            <h3>Clothing</h3>
            <hr class="mx-auto">
            <p>Here you can check out all our offerings</p>
        </div>
        <div class="container-fluid px-4"><div class="row">
            <?php include __DIR__ . '/server/get_clothing.php'; ?>
            <?php while ($row = $clothing_products->fetch_assoc()): ?>
                <div class="product text-center col-lg-3 col-md-4 col-sm-6 col-12">
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <div class="product-img-wrap">
                            <img src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                                 alt="<?= e((string)$row['product_name']) ?>" />
                        </div>
                    </a>
                    <div class="star">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <h5 class="p-name"><?= e((string)$row['product_name']) ?></h5>
                    <h4 class="p-price">$<?= e(number_format((float)$row['product_price'], 2)) ?></h4>
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <span class="buy-btn">Buy Now</span>
                    </a>
                </div>
            <?php endwhile; ?>
        </div></div>
    </section>

    <!-- Shoes -->
    <section id="shoes" class="my-5 pb-5">
        <div class="container text-center mt-5 py-5">
            <h3>Shoes</h3>
            <hr class="mx-auto">
            <p>Check out all our shoe offerings</p>
        </div>
        <div class="container-fluid px-4"><div class="row">
            <?php include __DIR__ . '/server/get_shoes.php'; ?>
            <?php while ($row = $shoes_products->fetch_assoc()): ?>
                <div class="product text-center col-lg-3 col-md-4 col-sm-6 col-12">
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <div class="product-img-wrap">
                            <img src="assets/imgs/<?= e((string)$row['product_image']) ?>"
                                 alt="<?= e((string)$row['product_name']) ?>" />
                        </div>
                    </a>
                    <div class="star">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <h5 class="p-name"><?= e((string)$row['product_name']) ?></h5>
                    <h4 class="p-price">$<?= e(number_format((float)$row['product_price'], 2)) ?></h4>
                    <a href="single_product.php?product_id=<?= e((string)(int)$row['product_id']) ?>">
                        <span class="buy-btn">Buy Now</span>
                    </a>
                </div>
            <?php endwhile; ?>
        </div></div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
