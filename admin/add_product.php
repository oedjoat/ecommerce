<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <h2>Create Product</h2>
            <div class="table-responsive">
                <div class="mx-auto container">
                    <form id="create-form" enctype="multipart/form-data" method="POST" action="create_product.php">
                        <?= csrf_field() ?>
                        <?php if (!empty($_GET['error'])): ?>
                            <p style="color:red;"><?= e((string)$_GET['error']) ?></p>
                        <?php endif; ?>

                        <div class="form-group mt-2">
                            <label>Title</label>
                            <input type="text" class="form-control" name="name" maxlength="100"
                                   placeholder="Title" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="4" maxlength="2000"
                                      placeholder="Description" required></textarea>
                        </div>
                        <div class="form-group mt-2">
                            <label>Price</label>
                            <input type="number" step="0.01" min="0" max="100000"
                                   class="form-control" name="price" placeholder="Price" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Special Offer (%)</label>
                            <input type="number" min="0" max="100"
                                   class="form-control" name="offer" placeholder="Sale %" value="0" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Category</label>
                            <select class="form-select" required name="category">
                                <option value="clothing">Clothing</option>
                                <option value="jackets">Jackets</option>
                                <option value="shoes">Shoes</option>
                                <option value="watches">Watches</option>
                            </select>
                        </div>
                        <div class="form-group mt-2">
                            <label>Color</label>
                            <input type="text" class="form-control" name="color" maxlength="40"
                                   placeholder="Color" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Image 1</label>
                            <input type="file" class="form-control" name="image1"
                                   accept="image/jpeg,image/png,image/webp" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Image 2</label>
                            <input type="file" class="form-control" name="image2"
                                   accept="image/jpeg,image/png,image/webp" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Image 3</label>
                            <input type="file" class="form-control" name="image3"
                                   accept="image/jpeg,image/png,image/webp" required />
                        </div>
                        <div class="form-group mt-2">
                            <label>Image 4</label>
                            <input type="file" class="form-control" name="image4"
                                   accept="image/jpeg,image/png,image/webp" required />
                        </div>

                        <div class="form-group mt-3">
                            <input type="submit" class="btn btn-primary" name="create_product" value="Create" />
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
