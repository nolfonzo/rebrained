def traverse(node):
    >>> node1=Node(1)
    >>> node2=Node(2)
    >>> node3=Node(3,node1,node2)
    >>> node4=Node(4)
    >>> node5=Node(5,node3,node4)
    >>> traverse(node5)
    5
    3
    1
    2
    4
    if node==None: return
    print node.value
    traverse(node.left)
    traverse(node.right)
 
class Node:
    def __init__(self,value,left=None,right=None):
        self.value=value;self.left=left;self.right=right

if __name__ == "__main__":
    import doctest
    doctest.testmod()


