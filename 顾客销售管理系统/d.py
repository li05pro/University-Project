products = [
    {"id": 1, "name": "笔记本电脑", "price": 5999, "stock": 20},
    {"id": 2, "name": "智能手机", "price": 3999, "stock": 30},
    {"id": 3, "name": "无线耳机", "price": 799, "stock": 50}
]
user_account = {"username": "li", "password": "123456"}
def login():
    username = input("请输入用户名：")
    password = input("请输入密码：")
    if username == user_account["username"] and password == user_account["password"]:
        print("登录成功！")
        return True
    else:
        print("用户名或密码错误，登录失败！")
        return False
def show_main_menu():
    print("\n===== 商品销售管理系统主菜单 =====")
    print("1. 显示商品信息")
    print("2. 添加商品信息")
    print("3. 删除商品信息")
    print("4. 退出程序")
    choice = input("请输入功能序号：")
    return choice
def show_products():
    """显示商品信息模块"""
    if not products:
        print("暂无商品信息！")
        return
    print("\n===== 商品信息列表 =====")
    print("ID\t名称\t\t价格\t库存")
    for prod in products:
        print(f"{prod['id']}\t{prod['name']}\t{prod['price']}\t{prod['stock']}")
def add_product():
    id = int(input("请输入商品ID："))
    name = input("请输入商品名称：")
    price = float(input("请输入商品价格："))
    stock = int(input("请输入商品库存："))
    products.append({"id": id, "name": name, "price": price, "stock": stock})
    print("商品添加成功！")
def delete_product():
    show_products()
    prod_id = int(input("请输入要删除的商品ID："))
    for i, prod in enumerate(products):
        if prod["id"] == prod_id:
            del products[i]
            print("商品删除成功！")
            return
    print("未找到该ID的商品，删除失败！")
def exit_program():

    print("感谢使用商品销售管理系统，再见！")
    exit()
def main():
    if not login():
        return
    while True:
        choice = show_main_menu()
        if choice == "1":
            show_products()
        elif choice == "2":
            add_product()
        elif choice == "3":
            delete_product()
        elif choice == "4":
            exit_program()
        else:
            print("输入无效，请重新选择！")
if __name__ == "__main__":
    main()