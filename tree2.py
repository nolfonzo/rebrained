def treeWalker(node):
    """
    >>> node1=Node(1)
    >>> node2=Node(2)
    >>> node3=Node(3,node1,node2)
    >>> node4=Node(4)
    >>> node5=Node(5,node3,node4)
    >>> treeWalker(node5)
    5
    3
    1
    2
    4
    :
    """
    lifo=[]
    while True:
        print node.value
        if node.left!=None:
            lifo.append(node)
            node=node.left
        else:
            try:
                node=lifo.pop()
            except: 
                return None
            node=node.right

class Node:
    def __init__(self,value,left=None,right=None):
        self.value=value;self.left=left;self.right=right

class Lifo:
    def __init__(self):
        self.lifo = ()
    def push(self, data):
        self.lifo = (data, self.lifo)
    def pop(self):
        if len(self.lifo)==0: return None
        ret, self.lifo = self.lifo
        return ret

if __name__ == "__main__":
    import doctest
    doctest.testmod()

